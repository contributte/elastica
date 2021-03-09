# Elastica

## Content

- [Setup](#usage)
- [Configuration](#configuration)
- [Usage](#usage)
- [Monolog](#monolog)

This extension integrates the [ruflin/elastica](https://github.com/ruflin/Elastica) into Nette Framework.
For more information on how to use Elastica [read the official documentation](http://Elastica.io/).

## Setup

```bash
composer require contributte/elastica
```
register extension
```yaml
extensions:
  elastica: Contributte\Elastica\DI\ElasticaExtension
```


## Configuration

Define at least one host, this would be minimal possible config.

```yaml
elastica:
  hosts:
    es01:
      host: localhost
```

Full config
```yaml
elastica:
  debug: %debugMode%
  hosts:
    es01:
      host: null
      port: null
      username: null
      password: null
      path: null
      url: null
      proxy: null
      persistent: null
      timeout: null
      connections: null
      roundRobin: null
      log: null
      retryOnConflict: null
      bigintConversion: null
```
Extension does not pass any unset values to elastica so elastica defaults just work.
Take a look to [Elastica docs](https://elastica-docs.readthedocs.io/en/latest/client.html#client-configurations).

## Usage

Extension registers `Contributte\Elastica\Client` to DI container.

```php
class YourService
{
	/** @var \Contributte\Elastica\Client */
	private $elasticaClient;

	public function __construct(Contributte\Elastica\Client $elastica)
	{
		$this->elasticaClient = $elastica;
	}
}
```

## Monolog
You can use monolog to log errors to kibana.

Just register ElasticaHandler in monolog setup.

 - Monolog\Handler\ElasticaHandler

-----

Inspired by [Filip Procházka](https://github.com/fprochazka) package [kdyby/ElasticSearch](https://github.com/Kdyby/ElasticSearch).

-----
