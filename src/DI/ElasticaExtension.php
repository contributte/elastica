<?php declare (strict_types = 1);

namespace Contributte\Elastica\DI;

use Contributte\Elastica\Client as ContributteClient;
use Contributte\Elastica\Diagnostics\Panel;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use stdClass;
use Tracy\Debugger;

/**
 * @property-read stdClass $config
 */
class ElasticaExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		$service = static fn () => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable();

		// https://github.com/ruflin/Elastica/blob/8.x/src/ClientConfiguration.php
		// https://github.com/ruflin/Elastica/blob/8.x/src/Client.php (_buildTransport)
		return Expect::structure([
			'debug' => Expect::bool(false),
			'logger' => $service(),
			'config' => Expect::structure([
				'hosts' => Expect::listOf(Expect::string()->dynamic()),
				'cloud_id' => Expect::string()->nullable()->dynamic(),
				'api_key' => Expect::string()->nullable()->dynamic(),
				'username' => Expect::string()->nullable()->dynamic(),
				'password' => Expect::string()->nullable()->dynamic(),
				'retries' => Expect::int()->nullable()->dynamic(),
				'retryOnConflict' => Expect::int(),
				'transport_config' => Expect::structure([
					'http_client' => $service(),
					'http_client_config' => Expect::arrayOf('mixed', 'string'),
					'http_client_options' => Expect::array(),
					'node_pool' => $service(),
				])->skipDefaults()->castTo('array'),
				'document' => Expect::structure([
					'autoPopulate' => Expect::bool(),
				])->skipDefaults()->castTo('array'),
			])->skipDefaults()->castTo('array'),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->config;

		/** @var array<string, mixed> $clientConfig */
		$clientConfig = $config->config;

		// Do not pass empty (null / []) values, so elastica defaults just work
		foreach (['transport_config', 'document'] as $key) {
			if (isset($clientConfig[$key]) && is_array($clientConfig[$key])) {
				$clientConfig[$key] = self::filterEmpty($clientConfig[$key]);
			}
		}

		$clientConfig = self::filterEmpty($clientConfig);

		$elastica = $builder->addDefinition($this->prefix('client'))
			->setFactory(ContributteClient::class, [
				'config' => $clientConfig,
				'logger' => $config->logger,
			]);

		if ($config->debug) {
			$builder->addDefinition($this->prefix('panel'))
				->setFactory(Panel::class);

			$elastica->addSetup($this->prefix('@panel') . '::register', ['@self']);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');
		$initialize->addBody('?::getBlueScreen()->addPanel(?);', [new Literal(Debugger::class), Panel::class . '::renderException']);
	}

	/**
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	private static function filterEmpty(array $values): array
	{
		foreach ($values as $key => $value) {
			if ($value === null || $value === []) {
				unset($values[$key]);
			}
		}

		return $values;
	}

}
