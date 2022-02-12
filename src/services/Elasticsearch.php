<?php

namespace buffalo\elasticsearch\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Entry;
use buffalo\elasticsearch\jobs\IndexElement as IndexElementJob;
use buffalo\elasticsearch\jobs\DeleteElement as DeleteElementJob;
use buffalo\elasticsearch\models\ElasticsearchResult;
use yii\helpers\Inflector;

class Elasticsearch extends Component
{
	private $client;
	private $siteIds;

	public function getClient() {
		if (!$this->client) {
			$this->client = \Elasticsearch\ClientBuilder::create()
				->setHosts([getenv('ELASTIC_SEARCH_HOST')])
				->build();
		}

		return $this->client;
	}

	public function search($search) {
		$response = $this->getClient()->search($search);

		return new ElasticsearchResult($response);
	}

	public function indexName(Element $element) {
		return implode('_', [
			getenv('ENVIRONMENT'),
			Inflector::underscore($element->section->handle),
			'entries',
			Inflector::underscore($element->site->handle),
		]);
	}

	public function enqueueAllReindexJobs()
	{
		$siteIds = $this->getSiteIds();
		$entries = [];

		foreach ($siteIds as $siteId) {
			$siteEntries = Entry::find()
				->siteId($siteId)
				->anyStatus()
				->all();

			$entries = array_merge($entries, $siteEntries);
		}

		foreach ($entries as $entry) {
			if ($entry->getStatus() == Entry::STATUS_LIVE) {
				$this->enqueueReindexJob($entry);
			} else {
				$this->enqueueDeleteJob($entry);
			}
		}
	}

	public function enqueueReindexJob($entry)
	{
		$job = new IndexElementJob([
			'elementId' => $entry->id,
			'siteId' => $entry->siteId,
		]);

		Craft::$app->getQueue()->push($job);
	}

	public function enqueueDeleteJob($entry)
	{
		$job = new DeleteElementJob([
			'elementId' => $entry->id,
			'indexName' => $this->indexName($entry),
		]);

		Craft::$app->getQueue()->push($job);
	}

	private function getSiteIds()
	{
		if (is_null($this->siteIds)) {
			$this->siteIds = Craft::$app->getSites()->getAllSiteIds();
		}

		return $this->siteIds;
	}
}
