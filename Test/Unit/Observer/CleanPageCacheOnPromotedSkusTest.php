<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Search\Model\Query;
use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Observer\CleanPageCacheOnPromotedSkus;

class CleanPageCacheOnPromotedSkusTest extends TestCase
{
    public function testCleansPageCacheWhenPromotedSkusChange(): void
    {
        $cacheTypeList = $this->createMock(TypeListInterface::class);
        $cacheTypeList->expects(self::once())->method('cleanType')->with('full_page');

        (new CleanPageCacheOnPromotedSkus($cacheTypeList))->execute($this->observer(null, 'SKU-1'));
    }

    public function testKeepsPageCacheWhenPromotedSkusAreUnchanged(): void
    {
        $cacheTypeList = $this->createMock(TypeListInterface::class);
        $cacheTypeList->expects(self::never())->method('cleanType');

        (new CleanPageCacheOnPromotedSkus($cacheTypeList))->execute($this->observer('SKU-1', 'SKU-1'));
        (new CleanPageCacheOnPromotedSkus($cacheTypeList))->execute($this->observer(null, ''));
    }

    private function observer(?string $before, string $after): Observer
    {
        $query = $this->createMock(Query::class);
        $query->method('getOrigData')->with('promoted_skus')->willReturn($before);
        $query->method('getData')->with('promoted_skus')->willReturn($after);
        return new Observer(['event' => new Event(['data_object' => $query])]);
    }
}
