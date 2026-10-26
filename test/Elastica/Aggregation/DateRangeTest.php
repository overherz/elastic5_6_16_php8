<?php

namespace Elastica\Test\Aggregation;

use Elastica\Aggregation\DateRange;
use Elastica\Document;
use Elastica\Query;
use Elastica\Type\Mapping;

class DateRangeTest extends BaseAggregationTest
{
    protected function _getIndexForTest()
    {
        $index = $this->_createIndex();
        $type = $index->getType('doc');

        $type->setMapping(new Mapping(null, [
            'created' => ['type' => 'date', 'format' => 'epoch_millis'],
        ]));

        $type->addDocuments([
            new Document(1, ['created' => 1390962135000]),
            new Document(2, ['created' => 1390965735000]),
            new Document(3, ['created' => 1390954935000]),
        ]);

        $index->refresh();

        return $index;
    }

    /**
     * @group functional
     */
    public function testDateRangeAggregation()
    {
        $agg = new DateRange('date');
        $agg->setField('created');
        $agg->addRange(1390958535000)->addRange(null, 1390958535000);

        $query = new Query();
        $query->addAggregation($agg);
        $results = $this->_getIndexForTest()->search($query)->getAggregation('date');

        foreach ($results['buckets'] as $bucket) {
            if (array_key_exists('to', $bucket)) {
                $this->assertEquals(1, $bucket['doc_count']);
            } elseif (array_key_exists('from', $bucket)) {
                $this->assertEquals(2, $bucket['doc_count']);
            }
        }
    }

    /**
     * @group functional
     */
    public function testDateRangeSetFormat()
    {
        $agg = new DateRange('date');
        $agg->setField('created');
        $agg->addRange(1390958535000)->addRange(null, 1390958535000);
        $agg->setFormat('epoch_millis');

        $query = new Query();
        $query->addAggregation($agg);
        $results = $this->_getIndexForTest()->search($query)->getAggregation('date');

        $this->assertEquals('1390958535000', $results['buckets'][0]['to_as_string']);
    }

    /**
     * Elasticsearch 5.6 reads numeric to and from as epoch_millis whatever the format is,
     * the format only changes the *_as_string output (6.0 started to parse them with the format).
     *
     * @group functional
     */
    public function testDateRangeSetFormatOnlyAffectsOutput()
    {
        $agg = new DateRange('date');
        $agg->setField('created');
        $agg->addRange(1390958535000)->addRange(null, 1390958535000);
        $agg->setFormat('m-d-y');

        $query = new Query();
        $query->addAggregation($agg);
        $results = $this->_getIndexForTest()->search($query)->getAggregation('date');

        $this->assertEquals('22-29-2014', $results['buckets'][0]['to_as_string']);
    }
}
