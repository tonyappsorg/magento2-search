<?php
declare(strict_types=1);

namespace Tonyapps\Search\Model\Autocomplete;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Real products in the search dropdown. Uses the quick_search_container collection,
 * so typo fallback and promoted SKUs apply here exactly as on the results page.
 */
class ProductDataProvider implements DataProviderInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private QueryFactory $queryFactory,
        private ItemFactory $itemFactory,
        private Visibility $visibility,
        private Image $imageHelper,
        private PriceHelper $priceHelper,
        private ScopeConfigInterface $config
    ) {
    }

    public function getItems()
    {
        $limit = (int)$this->config->getValue('catalog/search/tonyapps_autocomplete_products', ScopeInterface::SCOPE_STORE);
        $text = $this->queryFactory->get()->getQueryText();
        if ($limit <= 0 || $text === '') {
            return [];
        }

        $collection = $this->collectionFactory->create()
            ->addSearchFilter($text)
            ->setVisibility($this->visibility->getVisibleInSearchIds())
            ->addAttributeToSelect(['name', 'thumbnail', 'price'])
            ->addUrlRewrite()
            ->setOrder('relevance', 'DESC')
            ->setPageSize($limit)
            ->setCurPage(1);

        $items = [];
        foreach ($collection as $product) {
            $items[] = $this->itemFactory->create([
                'title' => $product->getName(),
                'url' => $product->getProductUrl(),
                'image' => $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl(),
                'label' => $this->priceHelper->currency(
                    $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(),
                    true,
                    false
                ),
                'row_class' => 'tonyapps-qs-product',
            ]);
        }
        return $items;
    }
}
