<?php declare (strict_types = 1);

namespace Contributte\Elastica\DI;

use Contributte\Elastica\Client as ContributteClient;
use Contributte\Elastica\Diagnostics\Panel;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
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
		// https://elastica-docs.readthedocs.io/en/latest/client.html#client-configurations
		return Expect::structure([
			'debug' => Expect::bool(false),
			'hosts' => Expect::arrayOf(
				Expect::structure([
					'port' => Expect::int(),
					'path' => Expect::string(),
					'host' => Expect::string(),
					'url' => Expect::string(),
					'proxy' => Expect::string(),
					'transport' => Expect::string(),
					'persistent' => Expect::bool(),
					'timeout' => Expect::int(),
					'connections' => Expect::array(),
					'roundRobin' => Expect::bool(),
					'compression' => Expect::bool(),
					'log' => Expect::anyOf(Expect::bool(), Expect::string()),
					'retryOnConflict' => Expect::int(),
					'bigintConversion' => Expect::bool(),
					'username' => Expect::string(),
					'password' => Expect::string(),
				])->castTo('array')
			),
		]);
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$elastica = $builder->addDefinition($this->prefix('client'))
			->setFactory(ContributteClient::class, [$this->config->hosts]);

		if ($this->config->debug) {
			$builder->addDefinition($this->prefix('panel'))
				->setFactory(Panel::class);

			$elastica->addSetup($this->prefix('@panel') . '::register', ['@self']);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');
		$initialize->addBody('?::getBlueScreen()->addPanel(?);', [new PhpLiteral(Debugger::class), Panel::class . '::renderException']);
	}

}
