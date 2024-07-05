<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\events\CodeEvent;
use craftpulse\ats\services\SyncCodesService;
use craftpulse\ats\models\CodeModel;
use yii\queue\RetryableJobInterface;

class CodeJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int
     */
    public int $codeId;

    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var string|null
     */
    public ?string $handle = null;

    /**
     * @var CodeModel|null
     */
    public ?CodeModel $code = null;

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
        $code = $this->getCode();

        if ($code === null) {
            return;
        }

        $event = new CodeEvent([
            'code' => $code,
        ]);

        Ats::$plugin->codes->trigger(SyncCodesService::EVENT_BEFORE_SYNC_CODES, $event);
        Ats::$plugin->codes->saveCode($this->code, $this->handle);

        if (Ats::$plugin->codes->hasEventHandlers(SyncCodesService::EVENT_AFTER_SYNC_CODES)) {
            Ats::$plugin->codes->trigger(SyncCodesService::EVENT_AFTER_SYNC_CODES, new CodeEvent([
                'code' => $code,
            ]));
        }

        if (!$event->isValid) {
            return;
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('ats', "Syncing {$this->code->title}");
    }

    private function getCode(): ?CodeModel {
        // Check if code exists, if it exists map it to the existing one else create new
        if (!is_null($this->handle)) {
            $code = Ats::$plugin->codes->getCodeById($this->codeId, $this->handle);
        }

        if (!is_null($code)) {
            $this->code = $code;
        }

        return $this->code;
    }
}