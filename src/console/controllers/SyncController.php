<?php

namespace craftpulse\ats\console\controllers;

use Craft;
use craft\helpers\Console;
use craftpulse\ats\Ats;
use yii\console\Controller;
use yii\console\ExitCode;
/**
 * Jobs controller
 */
class SyncController extends Controller
{

    /**
     * @var bool Whether jobs should be only queued and not run.
     */
    public bool $queue = false;

    /**
     * @var bool Whether verbose output should be enabled
     */
    public bool $verbose = false;

    /**
     * @inheritdoc
     */

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'queue';
        $options[] = 'verbose';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getHelp(): string
    {
        return 'Ats actions.';
    }

    /**
     * @inheritdoc
     */
    public function getHelpSummary()
    {
        return $this->getHelp();
    }

    /**
     * Syncs all offices
     */
    public function actionSyncOffices(): int
    {
        $this->syncBranches();

        return ExitCode::OK;
    }

    /**
     * Syncs all sectors
     */
    public function actionSyncSectors(): int
    {
        $this->syncSectors();

        return ExitCode::OK;
    }

    /**
     * Syncs all functions
     */
    public function actionSyncFunctions(): int
    {
        $this->syncFunctions();

        return ExitCode::OK;
    }

    /**
     * Syncs all sectors
     */
    public function actionSyncVacancies(): int
    {
        $this->actionSyncVacancies();

        return ExitCode::OK;
    }

    private function syncBranches(): void
    {
        if($this->queue) {
            Ats::$plugin->offices->syncBranches();
        }
    }

    private function syncFunctions(): void
    {
        if($this->queue) {
            Ats::$plugin->functions->syncFunctions();
        }
    }

    private function syncSectors(): void
    {
        if($this->queue) {
            Ats::$plugin->sectors->syncSectors();
        }
    }

    private function syncCodes(): void
    {
        if($this->queue) {
            Ats::$plugin->codes->syncCodes();
        }
    }

    private function syncVacancies(): void
    {
        if($this->queue) {
            Ats::$plugin->vacancies->syncVacancies();
        }
    }

    /**
     * ats/jobs command

    public function actionIndex(): int
    {
        $jobs = new SyncJobsService();
        $jobs->fetchJobs();
        // ...
        return ExitCode::OK;
    }*/
}
