<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\events\FunctionEvent;
use craftpulse\ats\services\SyncFunctionsService;
use craftpulse\ats\models\FunctionModel;
use yii\queue\RetryableJobInterface;

class FunctionJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int
     */
    public int $functionId;

    /**
     * @var OfficeModel|null
     */
    public ?FunctionModel $function = null;

    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @inheritdoc
     */
    /*public function init(): void
    {
        $this->batchSize = 11;

        parent::init();
    }*/

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return 1000;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < 20;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $function = $this->getFunction();

        if ($function === null) {
            return;
        }

        $event = new FunctionEvent([
            'function' => $function,
        ]);

        Ats::$plugin->functions->trigger(SyncFunctionsService::EVENT_BEFORE_SYNC_FUNCTION, $event);
        Ats::$plugin->functions->saveFunction($this->function);

        if (Ats::$plugin->functions->hasEventHandlers(SyncFunctionsService::EVENT_AFTER_SYNC_FUNCTION)) {
            Ats::$plugin->functions->trigger(SyncFunctionsService::EVENT_AFTER_SYNC_FUNCTION, new FunctionEvent([
                'function' => $function,
            ]));
        }

        if (!$event->isValid) {
            return;
        }
    }

    /**
     * @inheritdoc - prep for batched jobs
     */
    /*public function after(): void
    {
        $branch = $this->getFunction();

        Ats::$plugin->functions->saveFunction($this->function);

        if (Ats::$plugin->functions->hasEventHandlers(SyncFunctionsService::EVENT_AFTER_SYNC_FUNCTION)) {
            Ats::$plugin->functions->trigger(SyncFunctionsService::EVENT_AFTER_SYNC_FUNCTION, new FunctionEvent([
                'function' => $function,
            ]));
        }
    }*/

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('ats', "Syncing {$this->function->title}");
    }

    private function getFunction(): ?FunctionModel {
        if ($this->function === null) {
            $this->function = Ats::$plugin->functions->getFunctionById($this->functionId);
        }

        return $this->function;
    }

}