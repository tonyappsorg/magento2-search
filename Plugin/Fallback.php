<?php
declare(strict_types=1);

namespace Tonyapps\Search\Plugin;

use Magento\Elasticsearch\SearchAdapter\Query\Builder\MatchQuery;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Store\Model\ScopeInterface;

/**
 * Zero hits on quick search -> run the same request once more with fuzzy matching.
 * Exact matching stays the default so "shirt" never drifts to "short" when shirts exist.
 */
class Fallback
{
    private bool $relaxed = false;

    public function __construct(private ScopeConfigInterface $config)
    {
    }

    public function aroundQuery(AdapterInterface $subject, callable $proceed, RequestInterface $request): QueryResponse
    {
        $response = $proceed($request);
        if ($this->relaxed
            || $response->getTotal() > 0
            || $request->getName() !== 'quick_search_container'
            || !$this->config->isSetFlag('catalog/search/tonyapps_fallback', ScopeInterface::SCOPE_STORE)
        ) {
            return $response;
        }

        $this->relaxed = true;
        try {
            return $proceed($request);
        } finally {
            $this->relaxed = false;
        }
    }

    public function afterBuild(MatchQuery $subject, array $result): array
    {
        return $this->relaxed ? self::fuzzify($result) : $result;
    }

    /** Phrase and prefix variants don't take fuzziness, so they become plain fuzzy matches. */
    public static function fuzzify(array $query): array
    {
        foreach ($query['bool'] ?? [] as $condition => $clauses) {
            if ($condition === 'must_not' || !is_array($clauses)) {
                continue;
            }
            foreach ($clauses as $i => $clause) {
                $type = array_key_first($clause);
                if (!in_array($type, ['match', 'match_phrase', 'match_phrase_prefix'], true)) {
                    continue;
                }
                foreach ($clause[$type] as $field => $params) {
                    if (!is_string($params['query'] ?? null)) {
                        continue; // numeric/date values can't be fuzzy
                    }
                    unset($params['slop'], $params['max_expansions']);
                    $params += ['fuzziness' => 'AUTO', 'prefix_length' => 1, 'lenient' => true];
                    $query['bool'][$condition][$i] = ['match' => [$field => $params]];
                }
            }
        }
        return $query;
    }
}
