<?php

namespace buffalo\elasticsearch\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\base\Element;
use craft\base\Field;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Checkboxes;
use craft\fields\Color;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\fields\MultiSelect;
use craft\fields\RadioButtons;
use craft\fields\Tags;
use craft\fields\Table;
use craft\fields\Users;

class IndexElement extends BaseJob
{
    public $siteId;
    public $elementId;

    public function execute($queue)
    {
        $element = Craft::$app->getEntries()->getEntryById($this->elementId, $this->siteId);

        if (!$this->shouldIndex($element)) {
            return;
        }

        $es = \buffalo\elasticsearch\Elasticsearch::getInstance()->service;
        $document = $this->buildDocument($element, $es->indexName($element));
        $es->getClient()->index($document);

        // \craft\helpers\Console::output("Indexed entry #{$element->id}: {$element->title}");
    }

    protected function defaultDescription(): string
    {
        return 'Index entry in elasticsearch';
    }

    private function shouldIndex(Element $element)
    {
        return $element->enabledForSite && $element->hasContent();
    }

    private function buildDocument(Element $element, string $indexName)
    {
        return [
            'index' => $indexName,
            'type' => '_doc',
            'id' => $element->id,
            'body' => [
                'type' => [
                    'id' => $element->type->id,
                    'name' => $element->type->name,
                    'handle' => $element->type->handle,
                ],
                'author' => $element->author ? [
                    'id' => $element->author->id,
                    'name' => $element->author->name,
                    'username' => $element->author->username,
                ] : null,
                'section' => $element->section ? [
                    'id' => $element->section->id,
                    'name' => $element->section->name,
                    'handle' => $element->section->handle,
                ] : null,
                'status' => $element->status,
                'title' => $element->title,
                'slug' => $element->slug,
                'url' => $element->url,
                'content' => $this->getElementContent($element),
                'position' => $element->lft,
            ],
        ];
    }

    private function getElementContent(Element $element)
    {
        $content = [];

        foreach ($element->getFieldLayout()->fields as $field) {
            if (!$field->searchable) continue;

            $content[$field->handle] = $this->getFieldContent($field, $element[$field->handle]);
        }

        return $content;
    }


    private function getFieldContent(Field $field, $data)
    {
        // We don't have data at all then we just return null early. That avoids
        // us having to check repeatedly later on.
        if (is_null($data)) {
            return null;
        }

        if ($field instanceof Assets) {
            $assets = [];

            foreach ($data as $asset) {
                $assets[] = [
                    'id' => $asset->id,
                    'title' => $asset->title,
                    'url' => $asset->getUrl(),
                ];
            }

            return $assets;
        }

        if ($field instanceof Checkboxes || $field instanceof MultiSelect) {
            $options = [];

            foreach ($data as $option) {
                $options[] = [
                    'label' => $option->label,
                    'value' => $option->value,
                ];
            }

            return $options;
        }

        if ($field instanceof Color) {
            return [
                'hex' => str_replace('#', '', $data->hex),
            ];
        }

        if ($field instanceof Date) {
            return $data->format('c');
        }

        if ($field instanceof Dropdown || $field instanceof RadioButtons) {
            return [
                'label' => $data->label,
                'value' => $data->value,
            ];
        }

        if ($field instanceof Entries || $field instanceof Categories || $field instanceof Tags) {
            $elements = [];

            foreach ($data as $element) {
                $elements[] = [
                    'id' => $element->id,
                    'title' => $element->title,
                    'slug' => $element->slug,
                ];
            }

            return $elements;
        }

        if ($field instanceof Matrix) {
            $blocks = [];

            foreach ($data as $block) {
                $blocks[] = $this->getElementContent($block);
            }

            return $blocks;
        }

        if ($field instanceof Users) {
            $users = [];

            foreach ($data as $user) {
                $users[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                ];
            }

            return $users;
        }

        if ($field instanceof Table) {
            $rows = [];

            foreach($data as $row) {
                foreach($field->columns as $i => $col) {
                    if ($col['type'] == 'number') {
                        $row[$col['handle']] = $row[$i] = (int)$row[$col['handle']];
                    }
                }
                $rows[] = $row;
            }

            return $rows;
        }

        return $data;
    }
}
