![](https://heatbadger.now.sh/github/readme/contributte/elastica/)

<p align=center>
  <a href="https://github.com/contributte/elastica/actions"><img src="https://github.com/contributte/elastica/workflows/build/badge.svg"></a>
  <a href="https://codecov.io/gh/contributte/elastica"><img src="https://badgen.net/codecov/c/github/contributte/elastica"></a>
  <a href="https://packagist.org/packages/contributte/elastica"><img src="https://badgen.net/packagist/dm/contributte/elastica"></a>
  <a href="https://packagist.org/packages/contributte/elastica"><img src="https://badgen.net/packagist/v/contributte/elastica"></a>
</p>
<p align=center>
  <a href="https://packagist.org/packages/contributte/elastica"><img src="https://badgen.net/packagist/php/contributte/elastica"></a>
  <a href="https://github.com/contributte/elastica"><img src="https://badgen.net/github/license/contributte/elastica"></a>
  <a href="https://bit.ly/ctteg"><img src="https://badgen.net/badge/support/gitter/cyan"></a>
  <a href="https://bit.ly/cttfo"><img src="https://badgen.net/badge/support/forum/yellow"></a>
  <a href="https://contributte.org/partners.html"><img src="https://badgen.net/badge/sponsor/donations/F96854"></a>
</p>

<p align=center>
Website 🚀 <a href="https://contributte.org">contributte.org</a> | Contact 👨🏻‍💻 <a href="https://f3l1x.io">f3l1x.io</a> | Twitter 🐦 <a href="https://twitter.com/contributte">@contributte</a>
</p>

Nette Framework extension for integrating the [ruflin/elastica](https://github.com/ruflin/Elastica) Elasticsearch client.

For more information on how to use Elastica, read the [official documentation](https://elastica.io/).

## Versions

| State       | Version | Branch   | Nette | PHP     |
|-------------|---------|----------|-------|---------|
| dev         | `^2.1`  | `master` | 3.1+  | `>=8.1` |
| stable      | `^2.0`  | `master` | 3.1+  | `>=8.1` |

## Installation

To install latest version of `contributte/elastica` use [Composer](https://getcomposer.org).

```bash
composer require contributte/elastica
```

Register extension:

```neon
extensions:
	elastica: Contributte\Elastica\DI\ElasticaExtension
```

## Configuration

Define at least one host, this would be minimal possible config.

```neon
elastica:
	config:
		host: localhost
```

Full config with all possible options.

```neon
elastica:
	debug: %debugMode%
	config:
		host: null
		port: null
		path: null
		url: null
		proxy: null
		transport: null
		compression: false
		persistent: true
		timeout: null
		connections: []
		roundRobin: null
		retryOnConflict: 0
		bigintConversion: null
		username: null
		password: null
		auth_type: null
		curl: []
		headers: []
```

Extension does not pass any unset values to elastica so elastica defaults just work.
Take a look at [Elastica docs](https://elastica-docs.readthedocs.io/en/latest/client.html#client-configurations).

In a Docker environment, you can use `host: elasticsearch` and `port: 9200`, for example.

## Usage

The extension registers `Contributte\Elastica\Client` in the DI container, so you can inject it into your services.

```php
class YourService
{
	private Contributte\Elastica\Client $elasticaClient;

	public function __construct(Contributte\Elastica\Client $elastica)
	{
		$this->elasticaClient = $elastica;
	}
}
```

## Monolog

You can use Monolog to log errors to Kibana.

Just register ElasticaHandler in monolog setup.

- `Monolog\Handler\ElasticaHandler`

## Inspiration

Inspired by [Filip Procházka](https://github.com/fprochazka) package [kdyby/ElasticSearch](https://github.com/Kdyby/ElasticSearch).

## Development

See [how to contribute](https://contributte.org/contributing.html) to this package.

This package is currently maintained by these authors.

<a href="https://github.com/dakorpar">
 <img width="80" height="80" src="https://avatars0.githubusercontent.com/u/9303856?v=3&s=80">
</a>

-----

Consider to [support](https://contributte.org/partners.html) **contributte** development team.
Also thank you for using this package.
