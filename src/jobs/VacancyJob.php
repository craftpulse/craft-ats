<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\events\VacancyEvent;
use craftpulse\ats\models\VacancyModel;
use craftpulse\ats\services\SyncVacanciesService;
use yii\queue\RetryableJobInterface;

class VacancyJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int
     */
    public int $vacancyId;

    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var object|null
     */
    public ?object $vacancy = null;

    /**
     * @var bool
     */
    public bool $enabled = true;

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
        $vacancy = $this->vacancy;

        if ($vacancy === null) {
            return;
        }

        $event = new VacancyEvent([
            'vacancy' => $vacancy,
        ]);

        Ats::$plugin->vacancies->trigger(SyncVacanciesService::EVENT_BEFORE_SYNC_VACANCY, $event);
        Ats::$plugin->pratoMapper->syncVacancy($vacancy, $this->office, $this->enabled);

        if (Ats::$plugin->vacancies->hasEventHandlers(SyncVacanciesService::EVENT_AFTER_SYNC_VACANCY)) {
            Ats::$plugin->vacancies->trigger(SyncVacanciesService::EVENT_AFTER_SYNC_VACANCY, new VacancyEvent([
                'vacancy' => $vacancy,
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
        return Craft::t('ats', "Syncing {$this->vacancy->name}");
    }

}
