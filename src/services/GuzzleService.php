<?php

namespace craftpulse\ats\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craftpulse\ats\Ats;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use yii\log\Logger;

class GuzzleService extends Component
{

    /**
     * @param $service
     * @return boolean
     */
    public function createGuzzleClient(array $headers = [], array $config = [], string $endpoint = '', array $params = [], string $method = 'GET', ): ?Client
    {

        $client = Craft::createGuzzleClient([
            'base_uri' => $config['base_uri'],
            'headers' => $config['headers'],
        ]);

        /*Craft::dd([
            'method' => $method,
            'endpoint' => $endpoint,
            'config' => $config,
            'params' => json_encode($params),
        ]);*/

        // Create a pool of requests
        /*$pool = new Pool($client, $requests, [
            'fulfilled' => function () use (&$response) {
                Craft::dd($response->getBody());
            },
            'rejected' => function ($reason) {
                if($reason instanceof RequestException) {
                    /** RequestException $reason */
                    /*preg_match('/^(.*?)\R/', $reason->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Ats::$plugin->log(trim($matches[1], ':'), [], Logger::LEVEL_ERROR);
                    }
                }
            }

        ]);*/

        // Initiate the transfers and wait for the pool of requests to complete
        //$pool->promise()->wait();

        return $client;
    }
}