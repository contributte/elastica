<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client returning canned responses, so no Elasticsearch server is needed
 */
final class FakeHttpClient implements ClientInterface
{

	/** @var list<RequestInterface> */
	public array $requests = [];

	/** @var list<array{int, string}> */
	private array $responses = [];

	public function addResponse(int $status, string $body): self
	{
		$this->responses[] = [$status, $body];

		return $this;
	}

	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$this->requests[] = $request;

		[$status, $body] = array_shift($this->responses) ?? [200, '{}'];

		return new Response($status, [
			'Content-Type' => 'application/json',
			'X-Elastic-Product' => 'Elasticsearch',
		], $body);
	}

}
