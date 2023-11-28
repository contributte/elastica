<?php declare (strict_types = 1);

namespace Contributte\Elastica;

use Elastica\Client as ElasticaClient;
use Elastica\Request;
use Elastica\Response;
use Nette\SmartObject;
use Throwable;

class Client extends ElasticaClient
{

	use SmartObject;

	/** @var callable[] */
	public array $onSuccess = [];

	/** @var callable[] */
	public array $onFailure = [];

	/**
	 * {@inheritdoc}
	 */
	public function request(string $path, string $method = Request::GET, $data = [], array $query = [], string $contentType = Request::DEFAULT_CONTENT_TYPE): Response
	{
		$start = microtime(true);

		try {
			$response = parent::request($path, $method, $data, $query, $contentType);
			$this->onSuccess($this, $this->_lastRequest, $response, microtime(true) - $start);

			return $response;
		} catch (Throwable $e) {
			$this->onFailure($this, $this->_lastRequest, $e, microtime(true) - $start);

			throw $e;
		}
	}

}
