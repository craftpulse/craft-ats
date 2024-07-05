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

    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    public function syncCodes(callable $progressHandler = null, bool $queue = true): void
    {
        $this->provider->fetchCodes();
    }

    public function getCodeById(int $codeId, ?string $handle): ?CodeModel
    {
        if(!$codeId) {
            return null;
        }

        $codeRecord = Category::find()
            ->group($handle)
            ->codeId($codeId)
            ->anyStatus()
            ->one();

        if($codeRecord === null) {
            return null;
        }

        $code = new CodeModel();
        $code->setAttributes($codeRecord->getAttributes(), false);

        return $code;
    }

    /**
     * @throws ElementNotFoundException
     * @throws Throwable
     * @throws Exception
     */
    public function saveCode(CodeModel $code, ?string $handle = null): bool
    {
        if ($code->validate() === false) {
            return false;
        }

        if ($code->codeId) {
            $codeRecord = Category::find()
                ->group($handle)
                ->codeId($code->codeId)
                ->anyStatus()
                ->one();

            if ($codeRecord === null) {
                // CREATE NEW
                $category = Craft::$app->categories->getGroupByHandle($handle);

                if ($category) {
                    $codeRecord = new Category([
                        'groupId' => $category->id,
                    ]);
                }
            }

            $codeRecord->title = $code->title;
            $codeRecord->codeId = $code->codeId;
            $codeRecord->setEnabledForSite($codeRecord->getSupportedSites());
            $codeRecord->enabled = true;

            return Craft::$app->getElements()->saveElement($codeRecord);
        } else {
            // UPDATE
            var_dump('We update the code');
        }

        return false;
    }
}