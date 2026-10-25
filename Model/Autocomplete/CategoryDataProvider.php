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

/**
 * Categories whose name contains the typed text, limited to the current store's tree.
 * Titles carry the path below the store root ("Men › Tops › Jackets") so same-named categories can be told apart.
 */
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

        $rootId = (int)$store->getRootCategoryId();
        $ancestorNames = $this->getAncestorNames($collection, $rootId, (int)$store->getId());
        $items = [];
        foreach ($collection as $category) {
            $path = [];
            foreach ($this->getAncestorIds($category->getPath(), $rootId) as $id) {
                $path[] = $ancestorNames[$id] ?? '';
            }
            $path[] = $category->getName();
            $items[] = $this->itemFactory->create([
                'title' => implode(' › ', array_filter($path, 'strlen')),
                'url' => $category->getUrl(),
                'label' => __('Category')->render(),
                'row_class' => 'tonyapps-qs-category',
            ]);
        }
        return $items;
    }

    /** @return array<int, string> names of every ancestor below the store root, keyed by ID */
    private function getAncestorNames(iterable $categories, int $rootId, int $storeId): array
    {
        $ids = [];
        foreach ($categories as $category) {
            foreach ($this->getAncestorIds($category->getPath(), $rootId) as $id) {
                $ids[$id] = true;
            }
        }
        if (!$ids) {
            return [];
        }

        $names = [];
        $ancestors = $this->collectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIdFilter(array_keys($ids));
        foreach ($ancestors as $ancestor) {
            $names[(int)$ancestor->getId()] = (string)$ancestor->getName();
        }
        return $names;
    }

    /** "1/2/11/12/14" with root 2 -> [11, 12]: the ancestors between the store root and the category. */
    private function getAncestorIds(string $path, int $rootId): array
    {
        $ids = array_map('intval', explode('/', $path));
        $rootPos = array_search($rootId, $ids, true);
        return $rootPos === false ? [] : array_slice($ids, $rootPos + 1, -1);
    }
}
