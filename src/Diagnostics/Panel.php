<?php declare (strict_types = 1);

namespace Contributte\Elastica\Diagnostics;

use Contributte\Elastica\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastica\Exception\Bulk\ResponseException as BulkResponseException;
use Elastica\Exception\ExceptionInterface;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;

/**
 * @phpstan-type Query array{request: RequestInterface, response: ResponseInterface|null, time: float, exception: Throwable|null}
 */
class Panel implements IBarPanel
{

	public float $totalTime = 0;

	public int $queriesCount = 0;

	/** @var array<string, list<Query>> */
	public array $queries = [];

	private Client $client;

	/**
	 * @return array{tab: string, panel: string}|null
	 */
	public static function renderException(Throwable|null $e = null): ?array
	{
		$panel = '';

		if ($e instanceof ClientResponseException || $e instanceof ServerResponseException) {
			$response = $e->getResponse();

			$panel .= '<h3>Response</h3>';
			$panel .= Dumper::toHtml([
				'status' => $response->getStatusCode(),
				'body' => self::decodeBody($response),
			]);

		} elseif ($e instanceof BulkResponseException) {
			$panel .= '<h3>Failures</h3>';
			$panel .= Dumper::toHtml($e->getFailures());

		} elseif (!$e instanceof ExceptionInterface) {
			return null;
		}

		return $panel !== '' ? [
			'tab' => 'ElasticSearch',
			'panel' => $panel,
		] : null;
	}

	/**
	 * Decodes JSON (or NDJSON) body of PSR-7 message, falls back to raw string
	 */
	public static function decodeBody(MessageInterface $message): mixed
	{
		$body = $message->getBody();

		if ($body->isSeekable()) {
			$body->rewind();
		}

		$data = $body->getContents();

		if ($body->isSeekable()) {
			$body->rewind();
		}

		if ($data === '') {
			return null;
		}

		try {
			return Json::decode($data, true);
		} catch (JsonException) {
			try {
				return array_map(
					static fn (string $row): mixed => Json::decode($row, true),
					explode("\n", trim($data))
				);
			} catch (JsonException) {
				return $data;
			}
		}
	}

	public function getTab(): string
	{
		$img = Html::el('')->addHtml('<svg viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMinYMin meet"><path d="M255.96 134.393c0-21.521-13.373-40.117-33.223-47.43a75.239 75.239 0 0 0 1.253-13.791c0-39.909-32.386-72.295-72.295-72.295-23.193 0-44.923 11.074-58.505 30.088-6.686-5.224-14.835-7.94-23.402-7.94-21.104 0-38.446 17.133-38.446 38.446 0 4.597.836 9.194 2.298 13.373C13.582 81.739 0 100.962 0 122.274c0 21.522 13.373 40.327 33.431 47.64-.835 4.388-1.253 8.985-1.253 13.79 0 39.7 32.386 72.087 72.086 72.087 23.402 0 44.924-11.283 58.505-30.088 6.686 5.223 15.044 8.149 23.611 8.149 21.104 0 38.446-17.134 38.446-38.446 0-4.597-.836-9.194-2.298-13.373 19.64-7.104 33.431-26.327 33.431-47.64z" fill="#FFF"/><path d="M100.085 110.364l57.043 26.119 57.669-50.565a64.312 64.312 0 0 0 1.253-12.746c0-35.52-28.834-64.355-64.355-64.355-21.313 0-41.162 10.447-53.072 27.998l-9.612 49.73 11.074 23.82z" fill="#F4BD19"/><path d="M40.953 170.75c-.835 4.179-1.253 8.567-1.253 12.955 0 35.52 29.043 64.564 64.564 64.564 21.522 0 41.372-10.656 53.49-28.208l9.403-49.729-12.746-24.238-57.251-26.118-56.207 50.774z" fill="#3CBEB1"/><path d="M40.536 71.918l39.073 9.194 8.775-44.506c-5.432-4.179-11.91-6.268-18.805-6.268-16.925 0-30.924 13.79-30.924 30.924 0 3.552.627 7.313 1.88 10.656z" fill="#E9478C"/><path d="M37.192 81.32c-17.551 5.642-29.67 22.567-29.67 40.954 0 17.97 11.074 34.059 27.79 40.327l54.953-49.73-10.03-21.52-43.043-10.03z" fill="#2C458F"/><path d="M167.784 219.852c5.432 4.18 11.91 6.478 18.596 6.478 16.925 0 30.924-13.79 30.924-30.924 0-3.761-.627-7.314-1.88-10.657l-39.073-9.193-8.567 44.296z" fill="#95C63D"/><path d="M175.724 165.317l43.043 10.03c17.551-5.85 29.67-22.566 29.67-40.954 0-17.97-11.074-33.849-27.79-40.326l-56.415 49.311 11.492 21.94z" fill="#176655"/></svg>'); //phpcs: ignore
		$tab = Html::el('span')->title('Elastica')->addHtml($img);
		$title = Html::el('span')->class('tracy-label');

		if ($this->queriesCount > 0) {
			$title->setText(
				$this->queriesCount . ' call' . ($this->queriesCount > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime * 1000) . ' ms'
			);
		}

		return $tab->addHtml($title)->toHtml();
	}

	public function getPanel(): string
	{
		if ($this->queries === []) {
			return '';
		}

		$processedQueries = [];
		$allQueries = $this->queries;
		$totalTime = $this->totalTime; // @phpcs:ignore

		foreach ($allQueries as $authority => $requests) {
			foreach ($requests as $i => $item) {
				$processedQueries[$authority][$i] = $item;

				if ($item['exception'] !== null) {
					continue; // exception, do not re-execute
				}

				$request = $item['request'];

				if (stripos($request->getUri()->getPath(), '_search') === false || !in_array($request->getMethod(), ['GET', 'POST'], true)) {
					continue; // explain only search queries
				}

				try {
					$response = $this->client->sendRequest($this->withExplain($request));

					// replace the search response with the explained response
					$processedQueries[$authority][$i]['response'] = $response;
				} catch (Throwable) {
					// ignore
				}
			}
		}

		$decode = [self::class, 'decodeBody']; // @phpcs:ignore

		ob_start();

		require __DIR__ . '/panel.phtml';

		$result = ob_get_clean();

		return $result === false ? '' : $result;
	}

	public function success(Client $client, RequestInterface $request, Elasticsearch $response, float $time): void
	{
		$this->queries[$this->requestAuthority($request)][] = [
			'request' => $request,
			'response' => $response,
			'time' => $time,
			'exception' => null,
		];
		$this->totalTime += $time;
		$this->queriesCount++;
	}

	public function failure(Client $client, RequestInterface $request, Throwable $e, float $time): void
	{
		$response = $e instanceof ClientResponseException || $e instanceof ServerResponseException ? $e->getResponse() : null;

		$this->queries[$this->requestAuthority($request)][] = [
			'request' => $request,
			'response' => $response,
			'time' => $time,
			'exception' => $e,
		];
		$this->totalTime += $time;
		$this->queriesCount++;
	}

	public function register(Client $client): void
	{
		$this->client = $client;
		$client->onSuccess[] = [$this, 'success'];
		$client->onFailure[] = [$this, 'failure'];

		Debugger::getBar()->addPanel($this);
	}

	protected function requestAuthority(RequestInterface $request): string
	{
		$uri = $request->getUri();

		if ($uri->getHost() === '') {
			return 'http://localhost:9200';
		}

		// do not use getAuthority(), it contains user info (credentials)
		$port = $uri->getPort();

		return ($uri->getScheme() !== '' ? $uri->getScheme() : 'http') . '://' . $uri->getHost() . ($port !== null ? ':' . $port : '');
	}

	private function withExplain(RequestInterface $request): RequestInterface
	{
		$uri = $request->getUri();
		$query = [];
		parse_str($uri->getQuery(), $query);
		$query['explain'] = 'true';

		$body = $request->getBody();

		if ($body->isSeekable()) {
			$body->rewind();
		}

		return $request->withUri($uri->withQuery(http_build_query($query)));
	}

}
