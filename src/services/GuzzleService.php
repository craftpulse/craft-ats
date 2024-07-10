<?php

namespace craftpulse\ats\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Client;

class GuzzleService extends Component
{

    /**
     * @param array $config
     * @return Client|null
     */
    public function createGuzzleClient(array $config = []): ?Client
    {

        $client = Craft::createGuzzleClient([
            'base_uri' => $config['base_uri'],
            'headers' => $config['headers'] ?? null,
        ]);

        return $client;
    }
}
