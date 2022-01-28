<?php

namespace buffalo\elasticsearch\console\controllers;

use Craft;
use yii\console\Controller;
use buffalo\elasticsearch\Elasticsearch;

class ElasticsearchController extends Controller
{
	public function actionReindexAll(): int
	{
		Elasticsearch::getInstance()->service->enqueueAllReindexJobs();

		Craft::$app->queue->run();

		return 0;
	}
}
