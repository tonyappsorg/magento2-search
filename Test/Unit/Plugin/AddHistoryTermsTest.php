<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Plugin;

use Magento\Search\Model\Autocomplete\Item;
use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Plugin\AddHistoryTerms;

class AddHistoryTermsTest extends TestCase
{
    public function testHistoryFirstThenNewSuggestions(): void
    {
        $merged = AddHistoryTerms::merge($this->items('jacket', 'jackets'), $this->items('jade', 'jacket'), 0);

        $this->assertSame(['jacket', 'jackets', 'jade'], $this->titles($merged));
    }

    public function testDuplicatesDifferingInCaseKeepTheHistoryRow(): void
    {
        $history = [new Item(['title' => 'Jacket', 'num_results' => 23])];
        $merged = AddHistoryTerms::merge($history, $this->items('jacket'), 0);

        $this->assertCount(1, $merged);
        $this->assertSame(23, $merged[0]->getNumResults());
    }

    public function testLimitCapsTheMergedList(): void
    {
        $merged = AddHistoryTerms::merge($this->items('a1', 'a2'), $this->items('a3', 'a4'), 3);

        $this->assertSame(['a1', 'a2', 'a3'], $this->titles($merged));
    }

    public function testNoHistoryLeavesSuggestionsAsIs(): void
    {
        $this->assertSame(['jacket'], $this->titles(AddHistoryTerms::merge([], $this->items('jacket'), 8)));
    }

    private function items(string ...$titles): array
    {
        return array_map(fn (string $t) => new Item(['title' => $t]), $titles);
    }

    private function titles(array $items): array
    {
        return array_map(fn (Item $i) => $i->getTitle(), $items);
    }
}
