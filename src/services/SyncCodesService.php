<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\CodeModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

class SyncCodesService extends Component
{
    /**
     * @event CodeEvent
     */
    public const EVENT_BEFORE_SYNC_CODES = 'beforeSyncCodes';

    /**
     * @event CodeEvent
     */
    public const EVENT_AFTER_SYNC_CODES = 'afterSyncCodes';

    /**
     * @var null|object
     */
    public ?object $provider = null;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    /**
     * @param callable|null $progressHandler
     * @param bool $queue
     * @return void
     */
    public function syncCodes(callable $progressHandler = null, bool $queue = true): void
    {
        $this->provider->fetchCodes();
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getCodeByTitle(string $title, string $handle): ?CodeModel
    {
        if(!$title) {
            return null;
        }

        $codeRecord = Category::find()
            ->group($handle)
            ->title($title)
            ->status(null)
            ->one();

        $code = new CodeModel();

        if($codeRecord === null) {
            $code->title = $title;
            $this->saveCode($code, $handle, true);
        } else {
            $code->setAttributes($codeRecord->getAttributes(), false);
        }

        return $code;
    }

    /**
     * @throws ElementNotFoundException
     * @throws Throwable
     * @throws Exception
     */
    public function saveCode(CodeModel $code, string $handle, ?bool $isNew): bool
    {
        if ($code->validate() === false) {
            return false;
        }

        $codeRecord = null;

        if ($code->title && !$isNew) {
            // Look for it and update it.
            $codeRecord = Category::find()
                ->group($handle)
                ->title($code->title)
                ->status(null)
                ->one();

            // Create a new one, just in case it isn't found, but it was not labelled as new.
            if ($codeRecord === null) {
                $category = Craft::$app->categories->getGroupByHandle($handle);

                if ($category) {
                    $codeRecord = new Category([
                        'groupId' => $category->id,
                    ]);
                }
            }

            $codeRecord->title = $code->title;
            $codeRecord->setEnabledForSite($codeRecord->getSupportedSites());
            $codeRecord->enabled = true;
        } else {
            $category = Craft::$app->categories->getGroupByHandle($handle);

            if ($category !== null) {
                $codeRecord = new Category([
                    'groupId' => $category->id,
                ]);

                $codeRecord->title = $code->title;
                $codeRecord->enabled = true;
            }
        }

        if($codeRecord) {
            return Craft::$app->getElements()->saveElement($codeRecord);
        }

        return false;
    }
}
