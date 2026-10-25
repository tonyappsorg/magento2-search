<?php
declare(strict_types=1);

namespace Tonyapps\Search\Plugin;

use Magento\AdvancedSearch\Model\DataProvider\AutocompleteSuggestions;
use Magento\AdvancedSearch\Model\SuggestedQueriesInterface;
use Magento\CatalogSearch\Model\Autocomplete\DataProvider as HistoryTerms;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * With "Enable Search Suggestions" on, core fills the term rows from the OpenSearch suggester only, which offers
 * corrections ("jakcet" -> "jacket") but nothing for a correctly spelled word. Put core's own search-history
 * terms ("jacket", 23 results) first and keep the suggester's corrections after them.
 */
class AddHistoryTerms
{
    public function __construct(
        private HistoryTerms $historyTerms,
        private ScopeConfigInterface $config
    ) {
    }

    /**
     * @param ItemInterface[] $result
     * @return ItemInterface[]
     */
    public function afterGetItems(AutocompleteSuggestions $subject, array $result): array
    {
        // Suggestions off: core already returns the history terms.
        if (!$this->config->isSetFlag(SuggestedQueriesInterface::SEARCH_SUGGESTION_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return $result;
        }

        $limit = (int)$this->config->getValue(HistoryTerms::CONFIG_AUTOCOMPLETE_LIMIT, ScopeInterface::SCOPE_STORE);
        return self::merge($this->historyTerms->getItems(), $result, $limit);
    }

    /**
     * History first, then suggestions not already listed (case-insensitive), capped at $limit (0 = no cap).
     *
     * @param ItemInterface[] $history
     * @param ItemInterface[] $suggestions
     * @return ItemInterface[]
     */
    public static function merge(array $history, array $suggestions, int $limit): array
    {
        $items = [];
        foreach ([...$history, ...$suggestions] as $item) {
            $items[mb_strtolower((string)$item->getTitle())] ??= $item;
        }
        $items = array_values($items);
        return $limit > 0 ? array_slice($items, 0, $limit) : $items;
    }
}
