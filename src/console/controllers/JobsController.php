<?php

namespace craftpulse\ats\console\controllers;

use Craft;
use craft\console\Controller;
use craftpulse\ats\services\JobService;
use yii\console\ExitCode;

/**
 * Jobs controller
 */
class JobsController extends Controller
{
    public $defaultAction = 'index';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
        }
        return $options;
    }

    /**
     * ats/jobs command
     */
    public function actionIndex(): int
    {
        $jobService = new JobService();
        $jobService->fetchJobs();
        // ...
        return ExitCode::OK;
    }
}
