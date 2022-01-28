<?php

namespace buffalo\elasticsearch\jobs;

use Craft;
use craft\queue\BaseJob;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class DeleteElement extends BaseJob
{
    public $elementId;
    public $indexName;

    public function execute($queue)
    {
        $es = \buffalo\elasticsearch\Elasticsearch::getInstance()->service;

        try {
            $es->getClient()->delete([
                'index' => $this->indexName,
                'type' => '_doc',
                'id' => $this->elementId,
            ]);
        } catch (Missing404Exception $e) {
            // the document doesn't exist, but we want to delete it anyway.
        }
    }

    protected function defaultDescription(): string
    {
        return 'Delete entry from elasticsearch';
    }
}
