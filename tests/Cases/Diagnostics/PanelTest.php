<?php declare (strict_types = 1);

namespace Tests\Cases\Diagnostics;

use Contributte\Elastica\Client;
use Contributte\Elastica\Diagnostics\Panel;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastica\Query;
use Elastica\Query\MatchAll;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\FakeHttpClient;

require_once __DIR__ . '/../../bootstrap.php';

class PanelTest extends TestCase
{

	public function testSuccessAndFailure(): void
	{
		$http = new FakeHttpClient();
		$http->addResponse(200, '{"took":1,"timed_out":false,"_shards":{"total":1,"successful":1,"failed":0},"hits":{"total":{"value":0,"relation":"eq"},"max_score":null,"hits":[]}}');
		$http->addResponse(404, '{"error":{"type":"index_not_found_exception","reason":"no such index [missing]"},"status":404}');

		$client = new Client([
			'hosts' => ['http://elastic:secret@es.local:9200'],
			'transport_config' => ['http_client' => $http],
		]);

		$panel = new Panel();
		$panel->register($client);

		$client->getIndex('articles')->search(new Query(new MatchAll()));

		$e = Assert::exception(static function () use ($client): void {
			$client->getIndex('missing')->delete();
		}, ClientResponseException::class);

		Assert::same(2, $panel->queriesCount);
		Assert::same(['http://es.local:9200'], array_keys($panel->queries));

		[$success, $failure] = $panel->queries['http://es.local:9200'];
		Assert::same('/articles/_search', $success['request']->getUri()->getPath());
		Assert::same(200, $success['response']?->getStatusCode());
		Assert::null($success['exception']);
		Assert::same(404, $failure['response']?->getStatusCode());
		Assert::same($e, $failure['exception']);

		// Tracy tab and panel
		Assert::contains('2 calls', $panel->getTab());

		$html = $panel->getPanel();
		Assert::contains('/articles/_search', $html);
		Assert::contains('/missing', $html);
		Assert::notContains('secret', $html);

		// search query was re-executed with explain
		Assert::count(3, $http->requests);
		Assert::contains('explain=true', $http->requests[2]->getUri()->getQuery());

		// BlueScreen
		$bluescreen = Panel::renderException($e);
		Assert::notNull($bluescreen);
		Assert::contains('index_not_found_exception', $bluescreen['panel'] ?? '');
		Assert::null(Panel::renderException(new \RuntimeException('foo')));
	}

	public function testEmpty(): void
	{
		$panel = new Panel();

		Assert::same('', $panel->getPanel());
		Assert::notContains('call', $panel->getTab());
	}

}

(new PanelTest())->run();
