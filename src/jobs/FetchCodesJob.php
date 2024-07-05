<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use yii\queue\RetryableJobInterface;

class FetchCodesJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var array
     */
    public array $config;

    /**
     * @var array
     */
    public array $headers;

    /**
     * @var array
     */
    public array $params;

    /**
     * @var string
     */
    public string $endpoint;

    /**
     * @var string
     */
    public string $handle;

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
     */
    public function execute($queue): void
    {
        $client = Ats::$plugin->guzzleService->createGuzzleClient($this->headers, $this->config, $this->endpoint);

        if ($client === null) {
            return;
        }

        $response = $client->request($this->method, $this->endpoint, $this->params);
        $response = json_decode($response->getBody()->getContents());

        Ats::$plugin->pratoMapper->syncCodes($response, $this->office, $this->handle);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        // Add which office (the wiso thing) through App::env parser
        $name = App::parseEnv($this->office->officeCode);
        return Craft::t('ats', "Fetching all codes for {$name}");
    }
}