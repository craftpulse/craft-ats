<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use GuzzleHttp\Exception\GuzzleException;
use yii\queue\RetryableJobInterface;

class FetchBranchesJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var Array
     */
    public Array $config;

    /**
     * @var Array
     */
    public Array $headers;

    /**
     * @var string
     */
    public string $endpoint;

    /**
     * @var string
     */
    public string $method = 'GET';

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
     * @throws GuzzleException
     */
    public function execute($queue): void
    {
        $client = Ats::$plugin->guzzleService->createGuzzleClient($this->config);

        if ($client === null) {
            return;
        }

        $response = $client->request($this->method, $this->endpoint, []);
        $response = json_decode($response->getBody()->getContents());

        Ats::$plugin->pratoMapper->syncBranches($response, $this->office);
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
        // Add which office (the wiso thing) through App::env parser
        $name = App::parseEnv($this->office->officeCode);
        return Craft::t('ats', "Fetching all branches for {$name}");
    }

}
