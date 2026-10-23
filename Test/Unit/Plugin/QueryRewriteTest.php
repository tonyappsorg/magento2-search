<?php
declare(strict_types=1);

namespace Tonyapps\Search\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Tonyapps\Search\Plugin\Fallback;
use Tonyapps\Search\Model\Preprocessor\Stopwords;
use Tonyapps\Search\Plugin\Promote;

class QueryRewriteTest extends TestCase
{
    public function testFuzzifyTextClausesOnly(): void
    {
        $query = ['bool' => [
            'should' => [
                ['match' => ['name' => ['query' => 'jakcet', 'boost' => 2]]],
                ['match_phrase_prefix' => ['sku' => ['query' => 'MJ0', 'boost' => 1]]],
                ['match' => ['price' => ['query' => 42.0, 'boost' => 1]]],
            ],
            'must_not' => [['match' => ['name' => ['query' => 'kids']]]],
            'minimum_should_match' => 1,
        ]];

        $out = Fallback::fuzzify($query);

        $this->assertSame('AUTO', $out['bool']['should'][0]['match']['name']['fuzziness']);
        $this->assertSame(2, $out['bool']['should'][0]['match']['name']['boost']);
        $this->assertSame('AUTO', $out['bool']['should'][1]['match']['sku']['fuzziness'], 'prefix becomes fuzzy match');
        $this->assertArrayNotHasKey('fuzziness', $out['bool']['should'][2]['match']['price']);
        $this->assertSame($query['bool']['must_not'], $out['bool']['must_not']);
        $this->assertSame(1, $out['bool']['minimum_should_match']);
    }

    public function testPromotedIdsJoinTextShouldAndKeepFilters(): void
    {
        $filters = [['term' => ['visibility' => [3, 4]]]];
        $query = ['bool' => [
            'should' => [['match' => ['name' => ['query' => 'bag']]]],
            'must' => $filters,
            'minimum_should_match' => 1,
        ]];

        $out = Promote::boost(['body' => ['query' => $query]], [7, 3])['body']['query']['bool'];

        $this->assertSame($filters, $out['must'], 'filters untouched');
        $this->assertSame(1, $out['minimum_should_match']);
        $this->assertCount(3, $out['should']);
        [, $first, $second] = $out['should'];
        $this->assertSame(['7'], $first['constant_score']['filter']['ids']['values']);
        $this->assertGreaterThan($second['constant_score']['boost'], $first['constant_score']['boost']);
    }

    public function testBoostLeavesQueriesWithoutTextPartAlone(): void
    {
        $filterOnly = ['body' => ['query' => ['bool' => ['must' => [['term' => ['visibility' => 4]]]]]]];
        $this->assertSame($filterOnly, Promote::boost($filterOnly, [7]));
    }

    public function testParseStopwords(): void
    {
        $this->assertSame(['the', 'it', 'and'], Stopwords::parseWords(" The, it\n\nAND ,"));
        $this->assertSame([], Stopwords::parseWords(''));
    }

    public function testStopwordsMatchedCaseInsensitively(): void
    {
        $stopwords = $this->getMockBuilder(Stopwords::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStopwordsList'])
            ->getMock();
        $stopwords->method('getStopwordsList')->willReturn(['the', 'and']);

        $this->assertSame('Lando Jacket', $stopwords->process('The Lando AND Jacket'));
    }
}
