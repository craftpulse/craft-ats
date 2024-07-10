<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\events\BranchEvent;
use craftpulse\ats\services\SyncOfficesService;
use craftpulse\ats\models\OfficeModel;
use yii\queue\RetryableJobInterface;

class OfficeJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int
     */
    public int $branchId;

    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var OfficeModel|null
     */
    public ?OfficeModel $branch = null;

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
        $branch = $this->getBranch();

        if ($branch === null) {
            return;
        }

        $event = new BranchEvent([
            'branch' => $branch,
        ]);

        Ats::$plugin->offices->trigger(SyncOfficesService::EVENT_BEFORE_SYNC_BRANCH, $event);
        Ats::$plugin->offices->saveBranch($this->branch, $this->office);

        if (Ats::$plugin->offices->hasEventHandlers(SyncOfficesService::EVENT_AFTER_SYNC_BRANCH)) {
            Ats::$plugin->offices->trigger(SyncOfficesService::EVENT_AFTER_SYNC_BRANCH, new BranchEvent([
                'branch' => $branch,
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
        $branch = $this->getBranch();

        Ats::$plugin->offices->saveBranch($this->branch);

        if (Ats::$plugin->offices->hasEventHandlers(SyncOfficesService::EVENT_AFTER_SYNC_BRANCH)) {
            Ats::$plugin->offices->trigger(SyncOfficesService::EVENT_AFTER_SYNC_BRANCH, new BranchEvent([
                'branch' => $branch,
            ]));
        }
    }*/

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('ats', "Syncing {$this->branch->name}");
    }

    private function getBranch(): ?OfficeModel {
        // Check if branch exists, if it exists map it to the existing one else create new
        $branch = Ats::$plugin->offices->getBranchById($this->branchId);

        if (!is_null($branch)) {
            $this->branch = $branch;
        }

        return $this->branch;
    }

}
