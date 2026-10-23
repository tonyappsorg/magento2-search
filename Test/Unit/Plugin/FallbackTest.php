<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Plugin;

use Magento\Elasticsearch\SearchAdapter\Query\Builder\MatchQuery;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Plugin\Fallback;

class FallbackTest extends TestCase
{
    private const TEXT_QUERY = ['bool' => ['should' => [['match' => ['name' => ['query' => 'jakcet']]]]]];

    private Fallback $plugin;
    private AdapterInterface $adapter;
    private MatchQuery $matchQuery;

    protected function setUp(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturn(true);
        $this->plugin = new Fallback($config);
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->matchQuery = $this->createMock(MatchQuery::class);
    }

    public function testRetriesOnceWithFuzzyQueryOnZeroHits(): void
    {
        $calls = [];
        $proceed = function () use (&$calls) {
            // what the query builder would produce during this attempt
            $calls[] = $this->plugin->afterBuild($this->matchQuery, self::TEXT_QUERY);
            return $this->response(count($calls) === 1 ? 0 : 5);
        };

        $response = $this->plugin->aroundQuery($this->adapter, $proceed, $this->request('quick_search_container'));

        $this->assertSame(5, $response->getTotal());
        $this->assertCount(2, $calls);
        $this->assertSame(self::TEXT_QUERY, $calls[0], 'first attempt is exact');
        $this->assertSame('AUTO', $calls[1]['bool']['should'][0]['match']['name']['fuzziness']);
        $this->assertSame(self::TEXT_QUERY, $this->plugin->afterBuild($this->matchQuery, self::TEXT_QUERY), 'flag reset');
    }

    public function testNoRetryWhenStillEmpty(): void
    {
        $calls = 0;
        $proceed = function () use (&$calls) {
            $calls++;
            return $this->response(0);
        };

        $this->plugin->aroundQuery($this->adapter, $proceed, $this->request('quick_search_container'));

        $this->assertSame(2, $calls, 'one retry, never a loop');
    }

    public function testNoRetryWithHitsOrOtherRequests(): void
    {
        $calls = 0;
        $proceed = function (RequestInterface $request) use (&$calls) {
            $calls++;
            return $this->response($request->getName() === 'quick_search_container' ? 3 : 0);
        };

        $this->plugin->aroundQuery($this->adapter, $proceed, $this->request('quick_search_container'));
        $this->plugin->aroundQuery($this->adapter, $proceed, $this->request('catalog_view_container'));

        $this->assertSame(2, $calls);
    }

    public function testFlagResetsWhenRetryThrows(): void
    {
        $calls = 0;
        $proceed = function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('opensearch down');
            }
            return $this->response(0);
        };

        try {
            $this->plugin->aroundQuery($this->adapter, $proceed, $this->request('quick_search_container'));
            $this->fail('exception swallowed');
        } catch (\RuntimeException) {
        }

        $this->assertSame(self::TEXT_QUERY, $this->plugin->afterBuild($this->matchQuery, self::TEXT_QUERY));
    }

    private function request(string $name): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getName')->willReturn($name);
        return $request;
    }

    private function response(int $total): QueryResponse
    {
        return new QueryResponse([], $this->createMock(AggregationInterface::class), $total);
    }
}
