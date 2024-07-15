<?php

namespace craftpulse\ats\console\controllers;

use Craft;
use craft\helpers\Console;
use craftpulse\ats\Ats;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

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
    public function getHelpSummary(): string
    {
        return $this->getHelp();
    }

    /**
     * Synchronizes all the offices/branches
     */
    public function actionSyncOffices(): int
    {
        $this->syncBranches();

        return ExitCode::OK;
    }

    /**
     * Synchronizes all the vacancies.
     */
    public function actionSyncVacancies(): int
    {
        $this->syncVacancies();

        return ExitCode::OK;
    }

    /**
     * Handles setting the progress.
     */
    public function setProgressHandler(int $count, int $total): void
    {
        if ($this->verbose === false) {
            Console::updateProgress($count, $total);
        }
    }

    private function syncBranches(): void
    {
        if($this->queue) {
            Ats::$plugin->offices->syncBranches([$this, 'setProgressHandler']);
            $this->output('ATS offices queued for synchronization.');
        }

        $this->output('Branches successfully synced.');
    }

    private function syncVacancies(): void
    {
        if($this->queue) {
            Ats::$plugin->vacancies->syncVacancies([$this, 'setProgressHandler']);
            $this->output('ATS vacancies queued for synchronization.');
        }

        $this->output('Vacancies successfully synced.');
    }

    private function output(string $message): void
    {
        $this->stdout(Craft::t('ats', $message) . PHP_EOL, BaseConsole::FG_GREEN);
    }
}
