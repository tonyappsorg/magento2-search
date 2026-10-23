<?php
declare(strict_types=1);

namespace Tonyapps\Search\Plugin;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use Magento\Search\Model\QueryFactory;

/**
 * Promoted SKUs are stored on the search term itself (Marketing > Search Terms).
 * They are shown even when their text doesn't match, but still obey visibility,
 * category, price and layered-navigation filters.
 */
class Promote
{
    private const BOOST = 1000;

    public function __construct(
        private QueryFactory $queryFactory,
        private ProductResource $productResource
    ) {
    }

    public function afterBuildQuery(Mapper $subject, array $result, RequestInterface $request): array
    {
        if ($request->getName() !== 'quick_search_container') {
            return $result;
        }
        $skus = array_filter(array_map('trim', explode(',', (string)$this->queryFactory->get()->getData('promoted_skus'))));
        if (!$skus) {
            return $result;
        }
        $ids = $this->productResource->getProductsIdsBySkus($skus);
        $ordered = array_values(array_filter(array_map(fn ($sku) => $ids[$sku] ?? null, $skus)));

        return self::boost($result, $ordered);
    }

    /**
     * Core maps quick search to one flat bool: text matches in "should" (minimum_should_match 1),
     * filters in "must". An id clause in "should" therefore counts as a match while the filters
     * still apply. First id gets the biggest boost so admin order is kept.
     */
    public static function boost(array $result, array $ids): array
    {
        if (!$ids || !isset($result['body']['query']['bool']['should'])) {
            return $result;
        }
        foreach (array_values($ids) as $pos => $id) {
            $result['body']['query']['bool']['should'][] = ['constant_score' => [
                'filter' => ['ids' => ['values' => [(string)$id]]],
                'boost' => self::BOOST * (count($ids) - $pos),
            ]];
        }
        return $result;
    }
}
