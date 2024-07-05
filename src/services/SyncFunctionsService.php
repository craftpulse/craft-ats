<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\FunctionModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Functions Synchronisation service
 */
class SyncFunctionsService extends Component
{
    /**
     * @event FunctionEvent
     */
    public const EVENT_BEFORE_SYNC_FUNCTION = 'beforeSyncFunction';

    /**
     * @event FunctionEvent
     */
    public const EVENT_AFTER_SYNC_FUNCTION = 'afterSyncFunction';

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

    public function syncFunctions(callable $progressHandler = null, bool $queue = true): void {
        Ats::$plugin->pratoProvider->fetchFunctions();
    }

    public function getFunctionById(int $functionId): ?FunctionModel
    {
        if (!$functionId) {
            return null;
        }

        $functionRecord = Category::find()
            ->group(Ats::$plugin->settings->sectorHandle)
            ->functionId($functionId)
            ->anyStatus()
            ->one();

        if ($functionRecord === null) {
            return null;
        }

        $function = new FunctionModel();
        $function->setAttributes($functionRecord->getAttributes(), false);

        return $function;
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function saveFunction(FunctionModel $function): bool
    {
        if ($function->validate() === false) {
            return false;
        }

        if ($function->functionId) {
            $functionRecord = Category::find()
                ->group(Ats::$plugin->settings->sectorHandle)
                ->functionId($function->functionId)
                ->anyStatus()
                ->one();

            if ($functionRecord === null) {
                // CREATE NEW
                $category = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->sectorHandle);

                if ($category) {
                    $functionRecord = new Category([
                        'groupId' => $category->id
                    ]);
                }
            } else {
                // UPDATE
                var_dump('We update our functionnetje');
            }

            $functionRecord->title = $function->title;
            $functionRecord->functionId = $function->functionId;
            $functionRecord->setEnabledForSite($functionRecord->getSupportedSites());
            $functionRecord->enabled = true;

            $saved = Craft::$app->getElements()->saveElement($functionRecord);

            return $saved;
        }

        return false;
    }
}