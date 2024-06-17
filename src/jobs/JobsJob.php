<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\helpers\Logger;
use craftpulse\ats\providers\PratoFlexProvider;
use craftpulse\ats\services\JobService;

/**
 * Job queue job
 */
class JobsJob extends BaseJob
{
    function execute($queue): void
    {
        $logger = new Logger();
        $jobsService = new JobService();

        switch (Ats::$plugin->settings->atsProvider) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $jobs = $provider->fetchJobs();

        foreach($jobs as $i => $job) {
            $logger->stdout('↧ Upsert job: "'.$job->functionName.'"', $logger::FG_GREEN);
            $logger->stdout(PHP_EOL, $logger::RESET);

            $jobsService->upsertJob($job);

            $this->setProgress(
                $queue,
                $i / count($jobs),
                \Craft::t('ats', 'Jobs fetch: {step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => count($jobs),
                ])
            );
        }
    }

    protected function defaultDescription(): ?string
    {
        return \Craft::t('ats', 'Jobs fetch');
    }
}
