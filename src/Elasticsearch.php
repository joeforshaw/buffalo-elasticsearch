<?php
/**
 * elasticsearch plugin for Craft CMS 3.x
 *
 * Elasticsearch support for craft
 *
 * @link      https://builtbybuffalo.com/
 * @copyright Copyright (c) 2020 Built By Buffalo
 */

namespace buffalo\elasticsearch;


use Craft;
use craft\base\Plugin;
use craft\base\Element;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\elements\Entry;
use craft\console\Application as ConsoleApplication;
use yii\base\Event;
use buffalo\elasticsearch\services\Elasticsearch as ElasticsearchService;
use buffalo\elasticsearch\twigextensions\TwigExtension;

/**
 * Class Elasticsearch
 *
 * @author    Built By Buffalo
 * @package   Elasticsearch
 * @since     0.01
 *
 */
class Elasticsearch extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Elasticsearch
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '0.01';

    /**
     * @var bool
     */
    public $hasCpSettings = false;

    /**
     * @var bool
     */
    public $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'service' => ElasticsearchService::class,
        ]);

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'buffalo\elasticsearch\console\controllers';
        }

        if (Craft::$app->request->getIsSiteRequest()) {
            $extension = new TwigExtension();
            Craft::$app->view->registerTwigExtension($extension);
        }

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            // Entry creation/update
            Event::on(
                Entry::class,
                Entry::EVENT_AFTER_SAVE,
                function (Event $event) {
                    $entry = $event->sender;

                    if (!$entry->getIsDraft() && !$entry->getIsRevision()) {
                        if ($entry->enabled && $entry->enabledForSite) {
                            $this->service->enqueueReindexJob($entry, Entry::class);
                        } else {
                            $this->service->enqueueDeleteJob($entry, Entry::class);
                        }
                    }
                }
            );

            // Entry deletion
            Event::on(
                Entry::class,
                Entry::EVENT_AFTER_DELETE,
                function (Event $event) {
                    $entry = $event->sender;
                    if (!$entry->getIsDraft() && !$entry->getIsRevision()) {
                        $this->service->enqueueDeleteJob($entry, Entry::class);
                    }
                }
            );

            Event::on(
                Entry::class,
                Element::EVENT_AFTER_MOVE_IN_STRUCTURE,
                function(Event $event) {
                    $entries = Entry::find()->structureId($event->structureId)->all();

                    foreach($entries as $entry) {
                        $this->service->enqueueReindexJob($entry, Entry::class);
                    }
                }
            );
        }

        Craft::info(
            Craft::t(
                'buffalo-elasticsearch',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
