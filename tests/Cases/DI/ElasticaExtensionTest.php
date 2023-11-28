<?php declare (strict_types = 1);

namespace Tests\Cases\DI;

use Contributte\Elastica\Client;
use Contributte\Elastica\DI\ElasticaExtension;
use Contributte\Elastica\Diagnostics\Panel;
use Contributte\Tester\Utils\ContainerBuilder;
use Nette\DI\Compiler;
use Nette\DI\MissingServiceException;
use Tester\Assert;
use Tester\TestCase;

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

}

(new ElasticaExtensionTest())->run();
