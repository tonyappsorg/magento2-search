<?php
declare(strict_types=1);

namespace Tonyapps\Search\Model\Preprocessor;

use Magento\Elasticsearch\SearchAdapter\Query\Preprocessor\Stopwords as CoreStopwords;
use Magento\Elasticsearch\Model\Adapter\Index\Config\EsConfigInterface;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Core per-locale CSV list, plus/minus words entered per store view under
 * Catalog > Catalog Search. Query-time only, so changes need no reindex.
 */
class Stopwords extends CoreStopwords
{
    public function __construct(
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        ReadFactory $readFactory,
        Config $configCache,
        EsConfigInterface $esConfig,
        Reader $moduleDirReader,
        private ScopeConfigInterface $scopeConfig,
        $stopwordsModule = '',
        $stopwordsDirectory = '',
        ?SerializerInterface $serializer = null
    ) {
        parent::__construct(
            $storeManager,
            $localeResolver,
            $readFactory,
            $configCache,
            $esConfig,
            $moduleDirReader,
            $stopwordsModule,
            $stopwordsDirectory,
            $serializer
        );
    }

    /** Core compares case-sensitively, so "The" survived; compare lowercased. */
    public function process($query)
    {
        $stopwords = array_flip(array_map('mb_strtolower', $this->getStopwordsList()));
        $words = array_filter(explode(' ', (string)$query), fn ($w) => !isset($stopwords[mb_strtolower($w)]));
        return trim(implode(' ', $words));
    }

    protected function getStopwordsList()
    {
        $list = array_merge(parent::getStopwordsList(), $this->configWords('tonyapps_stopwords_add'));
        return array_values(array_diff(array_map('trim', $list), $this->configWords('tonyapps_stopwords_remove'), ['']));
    }

    private function configWords(string $field): array
    {
        $value = (string)$this->scopeConfig->getValue('catalog/search/' . $field, ScopeInterface::SCOPE_STORE);
        return self::parseWords($value);
    }

    /** Comma or newline separated, lowercased. */
    public static function parseWords(string $value): array
    {
        return array_values(array_filter(array_map(
            fn ($w) => mb_strtolower(trim($w)),
            preg_split('/[\s,]+/', $value) ?: []
        )));
    }
}
