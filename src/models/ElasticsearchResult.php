<?php

namespace buffalo\elasticsearch\models;

use buffalo\elasticsearch\jobs\DeleteElement;
use craft\elements\Entry;
use Craft;

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
        $hitIds = array_map(function($hit) { return $hit['_id']; }, $this->hits());

        $entries = Entry::find()->id($hitIds);
        if ($with) {
            $entries->with($with);
        }
        $entries = $entries->all();

        // Cleanup zombie index entries
        if (count($entries) < count($hitIds)) {
            $entryIds = array_map(function($e) { return strval($e->id); }, $entries);
            foreach (array_diff($hitIds, $entryIds) as $badId) {
                $this->cleanUpZombieIndex($badId);
            }
        }

        if (!$sort) return $entries;

        usort($entries, function($a, $b) use ($hitIds) {
            $a = array_search($a->id, $hitIds);
            $b = array_search($b->id, $hitIds);

            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });

        return $entries;
    }

    private function cleanUpZombieIndex($entryId) {
        $indexName = false;
        foreach ($this->hits() as $hit) {
            if ($hit['_id'] === $entryId) {
                $indexName = $hit['_index'];
            }
        }
        if ($indexName) {
            Craft::$app->getQueue()->push(new DeleteElement([
                'elementId' => $entryId,
                'indexName' => $indexName,
            ]));
        }
    }
}
