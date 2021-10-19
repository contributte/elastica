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
		// https://github.com/ruflin/Elastica/blob/master/src/ClientConfiguration.php#L26
		return Expect::structure([
			'debug' => Expect::bool(false),
			'config' => Expect::structure([
				'host' => Expect::string()->nullable()->dynamic(),
				'port' => Expect::int()->nullable()->dynamic(),
				'path' => Expect::string()->nullable(),
				'url' => Expect::string()->nullable(),
				'proxy' => Expect::string()->nullable(),
				'transport' => Expect::string()->nullable(),
				'persistent' => Expect::bool(),
				'timeout' => Expect::int()->nullable(),
				'connections' => Expect::array(), // host, port, path, transport, compression, persistent, timeout, username, password, auth_type, config -> (curl, headers, url)
				'roundRobin' => Expect::bool(),
				'retryOnConflict' => Expect::int(),
				'bigintConversion' => Expect::bool(),
				'username' => Expect::string()->nullable()->dynamic(),
				'password' => Expect::string()->nullable()->dynamic(),
				'auth_type' => Expect::string()->nullable()->dynamic(), //basic, digest, gssnegotiate, ntlm
			])->skipDefaults()->castTo('array'),
		]);
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$elastica = $builder->addDefinition($this->prefix('client'))
			->setFactory(ContributteClient::class, [$this->config->config]);

		if ($this->config->debug) {
			$builder->addDefinition($this->prefix('panel'))
				->setFactory(Panel::class);

			$elastica->addSetup($this->prefix('@panel') . '::register', ['@self']);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->methods['initialize'];
		$initialize->addBody('?::getBlueScreen()->addPanel(?);', [new PhpLiteral(Debugger::class), Panel::class . '::renderException']);
	}

}
