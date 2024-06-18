<?php

namespace craftpulse\ats\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Client;

class GuzzleService extends Component
{
    public function getConfig($key, $service = 'PratoFlex'): mixed
    {
        $settings = Ats::$plugin->getSettings();


    }

    /**
     * @param $service
     * @return Client
     */

    public function  createGuzzleClient($service = 'PratoFlex'): Client
    {
        $options = $this->getConfig('clientOptions', $service);

        return Craft::createGuzzleClient($options);
    }

    /**
     * @param DateTime
     */
}