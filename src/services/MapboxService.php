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

    /**
     * @const the mapbox forward geocoding endpoint
     */
    const MAPBOX_API_FORWARD_ENDPOINT = 'search/geocode/v6/forward';

    /**
     * @const the mapbox base url
     */
    const MAPBOX_API_BASE_URL='https://api.mapbox.com';

    /**
     * @const the language that needs to be fetched
     */
    const LANGUAGE = 'nl-BE';

    public function getGeoPoints($query): ?array
    {
        $config = [
            'base_uri' => self::MAPBOX_API_BASE_URL,
        ];
        $endpoint = self::MAPBOX_API_FORWARD_ENDPOINT;

        $queryParams = [
            'query' => [
                'q' => $query,
                'access_token' => $this->getApiKey(),
                'language' => self::LANGUAGE,
            ]
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);

        if ($client === null) {
            return null;
        }

        $response = $client->request('GET', $endpoint, $queryParams);
        $response = Json::decodeIfJson($response->getBody()->getContents());

        return $response['features'][0]['geometry']['coordinates'];
    }

    private function getApiKey(): string
    {
        return App::parseEnv(Ats::$plugin->settings->mapboxApiKey);
    }
}
