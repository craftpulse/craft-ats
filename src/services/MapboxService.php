<?php

namespace craftpulse\ats\services;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;
use craftpulse\ats\Ats;
use yii\base\Component;

/**
 * Mapbox Service service
 */
class MapboxService extends Component
{
    public function getApiKey(): string
    {
        return App::parseEnv(Ats::$plugin->settings->mapboxApiKey);
    }

    public function getAddress(string $query): ?array
    {
        try {
            $token = $this->getApiKey();
            $endpoint = "https://api.mapbox.com/search/geocode/v6/forward?q=${query}&access_token=${token}&language=nl-BE";

            $client = new \GuzzleHttp\Client();

            $response = $client->get($endpoint);
            $data = Json::decodeIfJson($response->getBody()->getContents(), true);

            return $data['features'][0];
        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return null;
    }

    public function getCoordsByLocation(?array $location, ?string $query = ''): ?array
    {
        if(!$location) {
            $location = $this->getAddress($query);
        }

        return $location['geometry']['coordinates'] ?? null;
    }
}
