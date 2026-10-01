<?php declare (strict_types = 1);

namespace Tests\Cases\DI;

use Contributte\Elastica\Client;
use Contributte\Elastica\DI\ElasticaExtension;
use Contributte\Elastica\Diagnostics\Panel;
use Contributte\Tester\Utils\ContainerBuilder;
use Contributte\Tester\Utils\Neonkit;
use Nette\DI\Compiler;
use Nette\DI\InvalidConfigurationException;
use Nette\DI\MissingServiceException;
use Psr\Log\NullLogger;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\FakeHttpClient;

require_once __DIR__ . '/../../bootstrap.php';

class ElasticaExtensionTest extends TestCase
{

	public function testRegisterServicesWithTracyPanel(): void
	{
		$container = ContainerBuilder::of()
			->withCompiler(static function (Compiler $compiler): void {
				$compiler->addExtension('elastica', new ElasticaExtension());
				$compiler->addConfig(['elastica' => ['debug' => true]]);
			})
			->build();

		Assert::type(Client::class, $container->getService('elastica.client'));
		Assert::type(Panel::class, $container->getService('elastica.panel'));
	}

	public function testWithoutTracyPanel(): void
	{
		Assert::exception(static function (): void {
			$container = ContainerBuilder::of()
				->withCompiler(static function (Compiler $compiler): void {
					$compiler->addExtension('elastica', new ElasticaExtension());
				})
				->build();

			$container->getService('elastica.panel');
		}, MissingServiceException::class);
	}

	public function testDefaultConfig(): void
	{
		$container = ContainerBuilder::of()
			->withCompiler(static function (Compiler $compiler): void {
				$compiler->addExtension('elastica', new ElasticaExtension());
			})
			->build();

		/** @var Client $client */
		$client = $container->getService('elastica.client');

		// elastica defaults are kept
		Assert::same(['localhost:9200'], $client->getConfig('hosts'));
		Assert::same(0, $client->getConfig('retryOnConflict'));
		Assert::same([], $client->getConfig('transport_config'));
	}

	public function testDocumentedConfig(): void
	{
		$container = ContainerBuilder::of()
			->withCompiler(static function (Compiler $compiler): void {
				$compiler->addExtension('elastica', new ElasticaExtension());
				$compiler->addConfig(Neonkit::load(<<<'NEON'
					elastica:
						debug: false
						logger: null
						config:
							hosts: []
							cloud_id: null
							api_key: null
							username: null
							password: null
							retries: null
							retryOnConflict: 0
							transport_config:
								http_client: null
								http_client_config: []
								http_client_options: []
								node_pool: null
							document:
								autoPopulate: false
				NEON));
			})
			->build();

		/** @var Client $client */
		$client = $container->getService('elastica.client');

		Assert::same(['localhost:9200'], $client->getConfig('hosts'));
		Assert::same([], $client->getConfig('transport_config'));
	}

	public function testFullConfig(): void
	{
		$container = ContainerBuilder::of()
			->withCompiler(static function (Compiler $compiler): void {
				$compiler->addExtension('elastica', new ElasticaExtension());
				$compiler->addConfig(Neonkit::load(<<<'NEON'
					services:
						http: Tests\Fixtures\FakeHttpClient
						logger: Psr\Log\NullLogger

					elastica:
						logger: @logger
						config:
							hosts:
								- http://es1.local:9200
								- http://es2.local:9200
							username: elastic
							password: secret
							retries: 1
							retryOnConflict: 3
							transport_config:
								http_client: @http
							document:
								autoPopulate: true
				NEON));
			})
			->build();

		/** @var Client $client */
		$client = $container->getService('elastica.client');

		Assert::same(['http://es1.local:9200', 'http://es2.local:9200'], $client->getConfig('hosts'));
		Assert::same('elastic', $client->getConfig('username'));
		Assert::same('secret', $client->getConfig('password'));
		Assert::same(1, $client->getConfig('retries'));
		Assert::same(3, $client->getConfig('retryOnConflict'));
		Assert::same(['autoPopulate' => true], $client->getConfig('document'));
		Assert::type(NullLogger::class, $client->getLogger());

		/** @var FakeHttpClient $http */
		$http = $container->getService('http');
		Assert::same($http, $client->getTransport()->getClient());

		$http->addResponse(200, '{"version":{"number":"8.11.0"}}');
		Assert::same('8.11.0', $client->getVersion());
		Assert::count(1, $http->requests);
		Assert::contains($http->requests[0]->getUri()->getHost(), ['es1.local', 'es2.local']);
	}

	public function testLegacyConfigIsRejected(): void
	{
		Assert::exception(static function (): void {
			ContainerBuilder::of()
				->withCompiler(static function (Compiler $compiler): void {
					$compiler->addExtension('elastica', new ElasticaExtension());
					$compiler->addConfig(['elastica' => ['config' => ['host' => 'localhost', 'port' => 9200]]]);
				})
				->build();
		}, InvalidConfigurationException::class, "Unexpected item 'elastica%a%config%a%host'%a%");
	}

}

(new ElasticaExtensionTest())->run();
