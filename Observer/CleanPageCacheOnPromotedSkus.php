<?php
declare(strict_types=1);

namespace Tonyapps\Search\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Popular search terms are full-page cached by core (Result\Index::getCacheableResult), and a newly
 * promoted product carries no cache tag of that page, so the page cache is cleaned when the list changes.
 */
class CleanPageCacheOnPromotedSkus implements ObserverInterface
{
    public function __construct(private TypeListInterface $cacheTypeList)
    {
    }

    public function execute(Observer $observer): void
    {
        $query = $observer->getEvent()->getData('data_object');
        if ($query && (string)$query->getOrigData('promoted_skus') !== (string)$query->getData('promoted_skus')) {
            $this->cacheTypeList->cleanType('full_page');
        }
    }
}
