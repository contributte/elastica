<?php declare (strict_types = 1);

namespace Contributte\Elastica;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastica\Client as ElasticaClient;
use Error;
use Nette\SmartObject;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * @method void onSuccess(Client $client, RequestInterface $request, Elasticsearch $response, float $time)
 * @method void onFailure(Client $client, RequestInterface $request, Throwable $e, float $time)
 */
class Client extends ElasticaClient
{

	use SmartObject;

	/** @var array<callable(Client, RequestInterface, Elasticsearch, float): void> */
	public array $onSuccess = [];

	/** @var array<callable(Client, RequestInterface, Throwable, float): void> */
	public array $onFailure = [];

	public function sendRequest(RequestInterface $request): Elasticsearch
	{
		$start = microtime(true);
		$previous = $this->getTransportLastRequest();

		try {
			$response = parent::sendRequest($request);
			$this->onSuccess($this, $this->resolveSentRequest($request, $previous), $response, microtime(true) - $start);

			return $response;
		} catch (Throwable $e) {
			$this->onFailure($this, $this->resolveSentRequest($request, $previous), $e, microtime(true) - $start);

			throw $e;
		}
	}

	/**
	 * Request built by elastica does not contain the node (host), it is resolved by transport,
	 * so prefer the request really sent by the transport.
	 */
	private function resolveSentRequest(RequestInterface $request, ?RequestInterface $previous): RequestInterface
	{
		$last = $this->getTransportLastRequest();

		return $last !== null && $last !== $previous ? $last : $request;
	}

	private function getTransportLastRequest(): ?RequestInterface
	{
		try {
			return $this->getTransport()->getLastRequest();
		} catch (Error) {
			// older elastic/transport 8.x throws on uninitialized typed property before the first request
			return null;
		}
	}

}
