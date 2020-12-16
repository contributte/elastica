<?php declare (strict_types = 1);

namespace Contributte\Elastica\Diagnostics;

use Contributte\Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;
use Exception;
use Nette;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Throwable;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;

/**
 * @codeCoverageIgnore
 */
class Panel implements IBarPanel
{

	use Nette\SmartObject;

	/** @var float */
	public $totalTime = 0;

	/** @var int */
	public $queriesCount = 0;

	/** @var mixed[] */
	public $queries = [];

	/** @var Client */
	private $client;

	public function getTab(): string
	{
		$img = Html::el('')->addHtml('<svg viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMinYMin meet"><path d="M255.96 134.393c0-21.521-13.373-40.117-33.223-47.43a75.239 75.239 0 0 0 1.253-13.791c0-39.909-32.386-72.295-72.295-72.295-23.193 0-44.923 11.074-58.505 30.088-6.686-5.224-14.835-7.94-23.402-7.94-21.104 0-38.446 17.133-38.446 38.446 0 4.597.836 9.194 2.298 13.373C13.582 81.739 0 100.962 0 122.274c0 21.522 13.373 40.327 33.431 47.64-.835 4.388-1.253 8.985-1.253 13.79 0 39.7 32.386 72.087 72.086 72.087 23.402 0 44.924-11.283 58.505-30.088 6.686 5.223 15.044 8.149 23.611 8.149 21.104 0 38.446-17.134 38.446-38.446 0-4.597-.836-9.194-2.298-13.373 19.64-7.104 33.431-26.327 33.431-47.64z" fill="#FFF"/><path d="M100.085 110.364l57.043 26.119 57.669-50.565a64.312 64.312 0 0 0 1.253-12.746c0-35.52-28.834-64.355-64.355-64.355-21.313 0-41.162 10.447-53.072 27.998l-9.612 49.73 11.074 23.82z" fill="#F4BD19"/><path d="M40.953 170.75c-.835 4.179-1.253 8.567-1.253 12.955 0 35.52 29.043 64.564 64.564 64.564 21.522 0 41.372-10.656 53.49-28.208l9.403-49.729-12.746-24.238-57.251-26.118-56.207 50.774z" fill="#3CBEB1"/><path d="M40.536 71.918l39.073 9.194 8.775-44.506c-5.432-4.179-11.91-6.268-18.805-6.268-16.925 0-30.924 13.79-30.924 30.924 0 3.552.627 7.313 1.88 10.656z" fill="#E9478C"/><path d="M37.192 81.32c-17.551 5.642-29.67 22.567-29.67 40.954 0 17.97 11.074 34.059 27.79 40.327l54.953-49.73-10.03-21.52-43.043-10.03z" fill="#2C458F"/><path d="M167.784 219.852c5.432 4.18 11.91 6.478 18.596 6.478 16.925 0 30.924-13.79 30.924-30.924 0-3.761-.627-7.314-1.88-10.657l-39.073-9.193-8.567 44.296z" fill="#95C63D"/><path d="M175.724 165.317l43.043 10.03c17.551-5.85 29.67-22.566 29.67-40.954 0-17.97-11.074-33.849-27.79-40.326l-56.415 49.311 11.492 21.94z" fill="#176655"/></svg>'); //phpcs: ignore
		$tab = Html::el('span')->title('Elastica')->addHtml($img);
		$title = Html::el('span')->class('tracy-label');

		if ($this->queriesCount) {
			$title->setText(
				$this->queriesCount . ' call' . ($this->queriesCount > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime * 1000) . ' ms'
			);
		}

		return $tab->addHtml($title)->toHtml();
	}


	public function getPanel(): ?string
	{
		if (!$this->queries) {
			return null;
		}

		/**
		 * @param Request|Response|mixed $object
		 */
		$extractData = function ($object) {
			if (!($object instanceof Request) && !($object instanceof Response)) {
				return [];
			}

			/** @var string|mixed[] $data */
			$data = $object->getData();

			try {
				return !is_array($data) ? Json::decode($data, Json::FORCE_ARRAY) : $data;
			} catch (Nette\Utils\JsonException $e) {
				try {
					return array_map(function ($row) {
						return Json::decode((string) $row, Json::FORCE_ARRAY);
					}, is_string($data) ? explode("\n", trim($data)) : []);
				} catch (Nette\Utils\JsonException $e) {
					return $data;
				}
			}
		};

		$processedQueries = [];
		$allQueries = $this->queries;
		$totalTime = $this->totalTime;

		foreach ($allQueries as $authority => $requests) {
			/** @var Request[] $item */
			foreach ($requests as $i => $item) {
				$processedQueries[$authority][$i] = $item;

				if (isset($item[3])) {
					continue; // exception, do not re-execute
				}

				if (stripos($item[0]->getPath(), '_search') === false || $item[0]->getMethod() !== 'GET') {
					continue; // explain only search queries
				}

				if (!is_array($data = $extractData($item[0]))) {
					continue;
				}

				try {
					$response = $this->client->request(
						$item[0]->getPath(),
						$item[0]->getMethod(),
						$item[0]->getData(),
						['explain' => 1] + $item[0]->getQuery()
					);

					// replace the search response with the explained response
					$processedQueries[$authority][$i][1] = $response;

				} catch (Throwable $e) {
					// ignore
				}
			}
		}

		ob_start();

		require __DIR__ . '/panel.phtml';

		$result = ob_get_clean();

		return $result === false ? null : $result;
	}


	public function success(Client $client, Request $request, Response $response, float $time): void
	{
		$this->queries[$this->requestAuthority($response)][] = [$request, $response, $time];
		$this->totalTime += $time;
		$this->queriesCount++;
	}


	public function failure(Client $client, Request $request, Throwable $e, float $time): void
	{
		/** @var Response $response */
		$response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

		$this->queries[$this->requestAuthority($response)][] = [$request, $response, $time, $e];
		$this->totalTime += $time;
		$this->queriesCount++;
	}



	protected function requestAuthority(?Response $response = null): string
	{
		if ($response) {
			$info = $response->getTransferInfo();
			$url = new Nette\Http\Url($info['url']);

		} else {
			/** @var string $current */
			$current = key($this->queries);
			$url = new Nette\Http\Url($current ?: 'http://localhost:9200/');
		}

		return $url->hostUrl;
	}

	/**
	 * @param Exception|Throwable $e
	 * @return array<string, string>|NULL
	 */
	public static function renderException($e = null): ?array
	{
		if (!$e instanceof ExceptionInterface) {
			return null;
		}

		$panel = null;

		if ($e instanceof ResponseException) {
			$panel .= '<h3>Request</h3>';
			$panel .= Dumper::toHtml($e->getRequest());

			$panel .= '<h3>Response</h3>';
			$panel .= Dumper::toHtml($e->getResponse());

		} elseif ($e instanceof \Elastica\Exception\Bulk\ResponseException) {
			$panel .= '<h3>Failures</h3>';
			$panel .= Dumper::toHtml($e->getFailures());

		}

		return $panel ? [
			'tab' => 'ElasticSearch',
			'panel' => $panel,
		] : null;
	}


	public function register(Client $client): void
	{
		$this->client = $client;
		$client->onSuccess[] = [$this, 'success'];
		$client->onFailure[] = [$this, 'failure'];

		Debugger::getBar()->addPanel($this);
	}

}
