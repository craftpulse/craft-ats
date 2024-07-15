<?php

namespace craftpulse\ats\utilities;

use Craft;
use craft\base\Utility;
use craftpulse\ats\Ats;
use craftpulse\ats\console\controllers\SyncController;

class SyncUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('ats', 'Sync');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'ats-sync';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('ats/_utilities/sync', [
            'actions' => self::getActions(),
        ]);
    }

    /**
     * Returns available actions
     * Instruction text should match that of the sync controller.
     *
     * @see SyncController
     */
    public static function getActions(bool $showAll = false): array
    {
        $actions = [];

        $actions[] = [
            'id' => 'sync-offices',
            'label' => Craft::t('ats', 'Sync Offices'),
            'instructions' => Craft::t('ats', 'Synchronizes all offices.')
        ];

        $actions[] = [
            'id' => 'sync-vacancies',
            'label' => Craft::t('ats', 'Sync Jobs'),
            'instructions' => Craft::t('ats', 'Synchronizes all jobs.')
        ];

        return $actions;
    }
}
