<?php

namespace buffalo\elasticsearch\models;

use craft\elements\Entry;

class ElasticsearchResult
{
    public $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function hits()
    {
        return $this->response['hits']['hits'];
    }

    public function entries($sort = true, $with = [])
    {
        $hit_ids = array_map(function($hit) { return $hit['_id']; }, $this->hits());
        $entries = Entry::find()->id($hit_ids);
        if ($with) {
            $entries->with($with);
        }
        $entries = $entries->all();

        if ( ! $sort) return $entries;

        usort($entries, function($a, $b) use ($hit_ids) {
            $a = array_search($a->id, $hit_ids);
            $b = array_search($b->id, $hit_ids);

            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });

        return $entries;
    }
}
