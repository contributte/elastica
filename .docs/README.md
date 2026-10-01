# Elastica

## Content

- [Setup](#setup)
- [Configuration](#configuration)
- [Usage](#usage)
- [Events](#events)
- [Monolog](#monolog)

This extension integrates the [ruflin/elastica](https://github.com/ruflin/Elastica) into Nette Framework.
For more information on how to use Elastica [read the official documentation](http://Elastica.io/).

## Setup

```bash
composer require contributte/elastica
```

Elastica 8+ is built on top of [elastic/transport](https://github.com/elastic/elastic-transport-php), which needs a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client.
If you don't have one yet, install e.g. Guzzle (or `symfony/http-client`), it is discovered automatically.

```bash
composer require guzzlehttp/guzzle
```

Register extension

```neon
extensions:
	elastica: Contributte\Elastica\DI\ElasticaExtension
```

## Configuration

Define at least one host, this would be minimal possible config.

```neon
elastica:
	config:
		hosts:
			- http://localhost:9200
```

Full config with all possible options.

```neon
elastica:
	debug: %debugMode%

	# PSR-3 logger passed to Elastica client (and elastic/transport), e.g. @monolog.logger
	logger: null

	config:
		hosts: [] # list of hosts, e.g. [http://es1:9200, https://user:pass@es2:9200]
		cloud_id: null
		api_key: null
		username: null
		password: null
		retries: null # defaults to number of hosts
		retryOnConflict: 0
		transport_config:
			http_client: null # PSR-18 client service, e.g. @httpClient
			http_client_config: [] # adapter config, e.g. Elastic\Elasticsearch\Transport\Adapter\AdapterOptions
			http_client_options: [] # options passed to the HTTP client adapter
			node_pool: null # Elastic\Transport\NodePool\NodePoolInterface service
		document:
			autoPopulate: false
```

Extension does not pass any unset values to elastica so elastica defaults just work.
Take a look to [Elastica client configuration](https://github.com/ruflin/Elastica/blob/8.x/src/ClientConfiguration.php)
and [elastic/transport](https://github.com/elastic/elastic-transport-php) docs.

In docker environment you should use `hosts: [http://elasticsearch:9200]` for example.

### Upgrade from Elastica 7

Elastica 8 replaced its own connection layer with the official Elasticsearch client, so the client configuration changed
(see [UPGRADE-8.0](https://github.com/ruflin/Elastica/blob/8.x/UPGRADE-8.0.md)).

| Before (Elastica 7)                         | After (Elastica 8+)                                               |
|---------------------------------------------|-------------------------------------------------------------------|
| `host: es.local`, `port: 9200`              | `hosts: [http://es.local:9200]`                                   |
| `url: http://es.local:9200/`                | `hosts: [http://es.local:9200]`                                   |
| `connections: [{host: a}, {host: b}]`       | `hosts: [http://a:9200, http://b:9200]`                           |
| `roundRobin: true`                          | `transport_config: node_pool: @nodePool` (e.g. `SimpleNodePool` with `RoundRobin` selector) |
| `username`, `password`                      | unchanged (or credentials in host URL, or `api_key`)              |
| `auth_type`, `transport`, `proxy`, `curl`, `compression`, `persistent`, `timeout`, `headers`, `path`, `bigintConversion` | removed, configure the PSR-18 HTTP client instead (`transport_config: http_client` / `http_client_options`) |

Removed options are rejected by the configuration schema, so you get a clear error after upgrade.

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

## Events

`Contributte\Elastica\Client` triggers `onSuccess` and `onFailure` events for every request, which are used by the Tracy panel.

```php
use Elastic\Elasticsearch\Response\Elasticsearch;
use Psr\Http\Message\RequestInterface;

$client->onSuccess[] = function (Client $client, RequestInterface $request, Elasticsearch $response, float $time): void {};
$client->onFailure[] = function (Client $client, RequestInterface $request, Throwable $e, float $time): void {};
```

## Monolog

You can use monolog to log errors to kibana.

Just register ElasticaHandler in monolog setup.

 - Monolog\Handler\ElasticaHandler

## Inspiration

Inspired by [Filip Procházka](https://github.com/fprochazka) package [kdyby/ElasticSearch](https://github.com/Kdyby/ElasticSearch).
