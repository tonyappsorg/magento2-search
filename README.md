# Tonyapps_Search

Smarter storefront search for Magento 2.4.8 on OpenSearch.

Core Magento plus OpenSearch already handles most of the feature list. This module adds only the parts core is missing:

| Feature | How |
|---|---|
| Full-text, multi-store, multilingual | Core. One index per store view. Stemmers and stop words are set per locale in `Magento_Elasticsearch/etc/esconfig.xml`. |
| Synonyms | Core: **Marketing > SEO & Search > Search Synonyms**. They are built into the index, so saving a group invalidates `catalogsearch_fulltext`. Cron's `indexer_reindex_all_invalid` rebuilds it, or run `bin/magento indexer:reindex catalogsearch_fulltext`. |
| **Stop words** | `Model\Preprocessor\Stopwords` (a preference on the core preprocessor). It takes core's CSV list for the locale, adds **Additional Stop Words** and removes **Words to Keep**, both set per store view. It also fixes core's case-sensitive matching, which let "The" through. Applied at query time, so no reindex is needed. |
| "Did you mean" / related terms | Core: **Catalog > Catalog Search > Enable Search Suggestions / Recommendations** (on by default). |
| Partial SKU / name | Core `quick_search_container` already runs `match_phrase_prefix` on name and sku. |
| Long-tail queries | Core. Minimum Should Match is empty by default, so a query matches when any one of its words matches. |
| **Product + category autocomplete** | `Model\Autocomplete\{Product,Category}DataProvider` are added to core `Magento\Search\Model\Autocomplete`. The `form-mini-mixin.js` mixin shows an image and price in each row, and clicking a row (or Enter on it) opens that page. Category rows show their path below the store root ("Men › Tops › Jackets"), so same-named categories can be told apart. |
| **Typo-tolerant fallback** | `Plugin\Fallback`. When a quick search finds nothing, it runs the same search once more with `fuzziness: AUTO`. Exact matching stays the default, so "shirt" won't turn into "short". A term rescued this way is saved with **0 results**, so the typo is never suggested and shows in **Marketing > Search Terms** as a 0-result term (a candidate for a synonym). |
| **Search-term rows for correct words** | `Plugin\AddHistoryTerms`. With "Enable Search Suggestions" on, core only shows the OpenSearch suggester's corrections, so a correctly spelled "jacket" got no term row. Core's own search-history terms ("jacket (23)") now come first, then the corrections, deduplicated and capped at the Autocomplete Limit. |
| **Promoted products** | `Plugin\Promote` plus a new `search_query.promoted_skus` column. Add SKUs on **Marketing > Search Terms > edit term**. The listed products are shown first, in the order you list them, even if they don't contain the search term. Visibility, category, price and layered-navigation filters still apply. |

## Configuration

**Stores > Configuration > Catalog > Catalog > Catalog Search** (store scope):

* Typo-Tolerant Fallback Search: default Yes
* Products in Autocomplete: default 5 (0 turns it off)
* Categories in Autocomplete: default 3 (0 turns it off)
* Additional Stop Words / Words to Keep: comma or newline separated

## Known limits

* Core caches the stop word list under one cache key for all locales. On a store with several languages, the first locale cached is used for up to 15 minutes. This is not fixed here.
* Promotions are read from the `q` request parameter through `QueryFactory`, so they don't apply to GraphQL search.

## Install

```
bin/magento module:enable Tonyapps_Search
bin/magento setup:upgrade
```

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/Search/Test/Unit
```

* Correction rows from OpenSearch's suggester are core and can be odd stems ("lavend", "lap"). Turn them off with **Enable Search Suggestions = No**; the history terms stay.

## Status (2026-10-25): done

Browser check (headless Chromium, desktop and 390px wide) on 2026-10-25:
* ArrowDown to a product row fills the input and selects the row. Enter opens that product.
* Enter on a core term row, or with nothing selected, runs a normal search.
* Escape closes the dropdown. Clicking a category row opens the category. No JS errors.
* At 390px the dropdown fits the viewport with no sideways scroll.
* Fixed during the check: "jacket" showed two identical "Jackets" category rows (Men and Women). Rows now show the full path.
* Added after the check: history term rows for correctly spelled words ("jacket" → "jacket (23)", "24-" → "24-MB0 (8)"), and typos rescued by the fallback are saved with 0 results ("jakcet" still finds 13 products on the page but is no longer suggested). 15 unit tests pass, and the browser run passes again, including Enter on a term row.

## Status (2026-09-29)

Verified live in DDEV on Luma with sample data:
* Autocomplete returns category and product rows with image and price.
* The typo fallback works: "jakcet" returns 23 results, the same as "jacket".
* Partial SKU works: "24-MB0" finds the bags.
* Promoted `24-MB01,MJ04-XS-Black` on "jacket": the duffle bag is first (the total goes from 23 to 24). The not-visible child SKU stays hidden.
* Stop words: adding "gym" takes "lando gym" from 27 results to 1. Keeping "the" takes "The lando" from 1 result to 172.
* Core synonyms: "parka,jacket" takes "parka" from 1 result to 17 after a reindex.
* The admin Promoted SKUs field renders with the saved value.
* The frontend mixin and its CSS are served.
* No exceptions were logged.
* Unit tests: 9 pass (fallback retry: once, only on 0 hits, flag reset even on error; query rewrites; stop words).

