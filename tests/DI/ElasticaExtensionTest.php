<?php declare (strict_types = 1);

namespace Tests\Elastica;

use Contributte\Elastica\Client;
use Contributte\Elastica\DI\ElasticaExtension;
use Contributte\Elastica\Diagnostics\Panel;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\MissingServiceException;
use PHPUnit\Framework\TestCase;

class ElasticaExtensionTest extends TestCase
{

	public function testRegisterServicesWithTracyPanel(): void
	{
		$config = new Configurator();
		$config->setTempDirectory(__DIR__ . '/../../temp');
		$config->onCompile[] = static function ($config, Compiler $compiler): void {
			$compiler->addExtension('elastica', new ElasticaExtension());
			$compiler->addConfig(['elastica' => ['debug' => true]]);
		};
		$container = $config->createContainer();

		$this->assertInstanceOf(Client::class, $container->getService('elastica.client'));
		$this->assertInstanceOf(Panel::class, $container->getService('elastica.panel'));
	}

	public function testWithoutTracyPanel(): void
	{
		$this->expectException(MissingServiceException::class);
		$config = new Configurator();
		$config->setTempDirectory(__DIR__ . '/../../temp');
		$config->onCompile[] = static function ($config, Compiler $compiler): void {
			$compiler->addExtension('elastica', new ElasticaExtension());
		};
		$container = $config->createContainer();
		$container->getService('elastica.tracypanel');
	}

}
