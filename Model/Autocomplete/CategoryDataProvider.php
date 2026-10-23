<?php
declare(strict_types=1);

namespace Tonyapps\Search\Model\Autocomplete;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/** Categories whose name contains the typed text, limited to the current store's tree. */
class CategoryDataProvider implements DataProviderInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private QueryFactory $queryFactory,
        private ItemFactory $itemFactory,
        private StoreManagerInterface $storeManager,
        private ScopeConfigInterface $config
    ) {
    }

    public function getItems()
    {
        $limit = (int)$this->config->getValue('catalog/search/tonyapps_autocomplete_categories', ScopeInterface::SCOPE_STORE);
        $text = $this->queryFactory->get()->getQueryText();
        if ($limit <= 0 || $text === '') {
            return [];
        }

        $store = $this->storeManager->getStore();
        $collection = $this->collectionFactory->create()
            ->setStoreId($store->getId())
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('name', ['like' => '%' . addcslashes($text, '%_\\') . '%'])
            ->addPathsFilter('1/' . $store->getRootCategoryId() . '/')
            ->addIsActiveFilter()
            ->addUrlRewriteToResult()
            ->setPageSize($limit);

        $items = [];
        foreach ($collection as $category) {
            $items[] = $this->itemFactory->create([
                'title' => $category->getName(),
                'url' => $category->getUrl(),
                'label' => __('Category')->render(),
                'row_class' => 'tonyapps-qs-category',
            ]);
        }
        return $items;
    }
}
