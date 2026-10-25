<?php
declare(strict_types=1);

namespace Tonyapps\Search\Plugin;

use Magento\Elasticsearch\SearchAdapter\Query\Builder\MatchQuery;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Search\Model\Query;
use Magento\Store\Model\ScopeInterface;

/**
 * Zero hits on quick search -> run the same request once more with fuzzy matching.
 * Exact matching stays the default so "shirt" never drifts to "short" when shirts exist.
 *
 * When the fuzzy retry is what found the products, the term is saved with 0 results: it really matched
 * nothing, so it must not be offered as an autocomplete term ("jakcet") and should show up as a 0-result
 * term in Marketing > Search Terms, where merchants add synonyms.
 */
class Fallback
{
    private bool $relaxed = false;
    private bool $rescued = false;

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
            $response = $proceed($request);
            $this->rescued = $response->getTotal() > 0;
            return $response;
        } finally {
            $this->relaxed = false;
        }
    }

    /** Core saves the hit count once per results page, after the search above. */
    public function beforeSaveNumResults(Query $subject, $numResults): array
    {
        return [$this->rescued ? 0 : $numResults];
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
