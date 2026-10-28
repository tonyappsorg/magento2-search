<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Plugin;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Search\RequestInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use Magento\OpenSearch\SearchAdapter\Mapper;
use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Plugin\Promote;

class PromotePluginTest extends TestCase
{
    public function testAddsPromotedSkuIdsOnlyToQuickSearch(): void
    {
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getData'])->getMock();
        $query->method('getData')->with('promoted_skus')->willReturn('SKU-A, SKU-B');
        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->expects(self::once())->method('get')->willReturn($query);
        $productResource = $this->createMock(ProductResource::class);
        $productResource->expects(self::once())->method('getProductsIdsBySkus')->with(['SKU-A', 'SKU-B'])
            ->willReturn(['SKU-A' => 17, 'SKU-B' => 23]);
        $plugin = new Promote($queryFactory, $productResource);
        $request = $this->createMock(RequestInterface::class);
        $request->method('getName')->willReturn('quick_search_container');
        $result = ['body' => ['query' => ['bool' => ['should' => [['match' => ['name' => ['query' => 'bag']]]], 'must' => [['term' => ['visibility' => 4]]], 'minimum_should_match' => 1]]]];

        $out = $plugin->afterBuildQuery($this->createMock(Mapper::class), $result, $request);

        $bool = $out['body']['query']['bool'];
        self::assertCount(3, $bool['should']);
        self::assertSame(['17'], $bool['should'][1]['constant_score']['filter']['ids']['values']);
        self::assertSame(['23'], $bool['should'][2]['constant_score']['filter']['ids']['values']);
        self::assertSame([['term' => ['visibility' => 4]]], $bool['must']);
    }

    public function testOtherSearchRequestsAreReturnedUntouched(): void
    {
        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->expects(self::never())->method('get');
        $resource = $this->createMock(ProductResource::class);
        $resource->expects(self::never())->method('getProductsIdsBySkus');
        $request = $this->createMock(RequestInterface::class);
        $request->method('getName')->willReturn('catalog_view_container');
        $result = ['body' => ['query' => ['bool' => ['should' => []]]]];

        self::assertSame($result, (new Promote($queryFactory, $resource))->afterBuildQuery($this->createMock(Mapper::class), $result, $request));
    }
}
