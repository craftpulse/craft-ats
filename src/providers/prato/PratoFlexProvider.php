<?php

namespace craftpulse\ats\providers\prato;

use craft\errors\ElementNotFoundException;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;
use craftpulse\ats\Ats;
use craftpulse\ats\jobs\FetchBranchesJob;
use craftpulse\ats\jobs\FetchVacanciesJob;
use craftpulse\ats\jobs\VacancyJob;
use craftpulse\ats\models\SettingsModel;

use CURLFile;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Throwable;

use yii\base\Component;
use yii\base\Exception;
use yii\base\ExitException;
use yii\log\Logger;

/**
 * Class PratoFlexProvider
 * @package craftpulse\ats\providers\prato
 *
 * @property-read array $offices
 * @property-read SettingsModel $settings
 */
class PratoFlexProvider extends Component
{

    /**
     * @const the API vacancy endpoint URI.
     */
    public const API_VACANCY_ENDPOINT = 'sollicitation/vacancies';

    /**
     * @const the API codes endpoint URI
     */
    public const API_CODES_ENDPOINT = 'sollicitation/codes';

    /**
     * @const the API users endpoint URI
     */
    public const API_USERS_ENDPOINT = 'sollicitation/users';

    /**
     * @const the API branches endpoint URI
     */
    public const API_BRANCHES_ENDPOINT = 'sollicitation/branches';

    /**
     * @const the API sectors endpoint URI
     */
    public const API_SECTORS_ENDPOINT = 'sollicitation/sectors';

    /**
     * @const the API subscriptions endpoint URI
     */
    public const API_SUBSCRIPTIONS_ENDPOINT = 'sollicitation/subscriptions';

    /**
     * @const the API language code
     */
    public const LANGUAGE_CODE = 'nl';

    /**
     * @const the API kind ids
     */
    public const KIND_ID = 'kindId';
    public const KIND_IDS = [
        'regime' => [
            'kindId' => '170',
        ],
        'workshift' => [
            'kindId' => '169',
        ],
        'contractType' => [
            'kindId' => '297',
        ],
        'sector' => [
            'kindId' => '67',
        ],
        'province' => [
            'kindId' => '52',
        ]
    ];

    /**
     * @var SettingsModel|null
     */
    private ?SettingsModel $settings = null;

    public function init(): void
    {
        parent::init();
        $this->settings = Ats::$plugin->settings;
    }

    /**
     * Fetches the jobs from PratoFlex and return the Job models as an array
     * @param object $office
     * @param array $data
     * @param string $method
     * @return object
     * @throws GuzzleException
     * @throws Throwable
     */
    public function pushUser(object $office, array $data, string $method = 'POST'): object
    {
        $headers = ['Content-Type' => 'application/json'];
        $headers['Authorization'] = 'WB ' . App::parseEnv($office->officeToken);
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT;

        $body = [
            'body' => Json::encode($data),
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);
        $response = $client->request($method, $endpoint, $body);
        $response = json_decode($response->getBody()->getContents());

        if(!empty($response)) {
            try {
                Ats::$plugin->users->updateUser($response);
            } catch (ElementNotFoundException|Exception $exception) {
                Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
            } catch (Throwable $exception) {
                Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
            }
        }

        return $response;
    }

    /**
     * @param object $office
     * @param array $data
     * @param int $userId
     * @param string $method
     * @return object
     * @throws GuzzleException
     */
    public function updateUser(object $office, array $data, int $userId, string $method = 'POST'): object
    {
        // endpoint building and prepping data
        $headers = [
            'Authorization: WB ' . App::parseEnv($office->officeToken),
        ];
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT . '/' . $userId;

        $body = [
            'body' => Json::encode($data),
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);
        $response = $client->request($method, $endpoint, $body);
        return json_decode($response->getBody()->getContents());
    }

    /**
     * @param object $office
     * @param int $userId
     * @param CURLFile $cv
     * @return void
     */
    public function pushCvToUser(object $office, int $userId, CURLFile $cv): void
    {
        // endpoint building and prepping data
        $headers = [
            'Authorization: WB ' . App::parseEnv($office->officeToken),
        ];
        $base_uri = App::parseEnv($this->settings->pratoFlexBaseUrl);
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT . '/' . $userId . '/cvs';
        $body = [
            'cvfile' => $cv,
        ];

        // prepare the CURL
        $request = curl_init($base_uri . $endpoint);

        // Add CURL options
        curl_setopt($request, CURLOPT_POST, 1);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);

        // Get the result
        $result = curl_exec($request);

        // Close the stream
        curl_close($request);
    }

    /**
     * Push the application to pratoflex
     * @param object $office
     * @param int $userId
     * @param array $applicationData
     * @param string $method
     * @return object|bool
     * @throws GuzzleException
     * @throws Throwable
     */
    public function pushApplication(object $office, int $userId, array $applicationData, string $method = 'POST'): object|bool
    {
        $headers = ['Content-Type' => 'application/json'];
        $headers['Authorization'] = 'WB ' . App::parseEnv($office->officeToken);
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT . '/' . $userId . '/sollicitations';

        $body = [
            'body' => Json::encode($applicationData),
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);
        $response = $client->request($method, $endpoint, $body);
        $response = json_decode($response->getBody()->getContents());

        if(!empty($response)) {
            try {
                Ats::$plugin->users->updateUser($response);
            } catch (ElementNotFoundException|Exception $exception) {
                Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_WARNING);
                // return false, so we know it did not exist in pratoFlex
                return false;
            } catch (Throwable $exception) {
                Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
            }
        }

        return $response;
    }

    /**
     * @param string $method
     * @return void
     */
    public function fetchBranches(string $method = 'GET'): void
    {
        $offices = $this->settings->officeCodes ?? null;

        if(!is_null($offices)) {
            foreach($offices as $office) {
                $office = (object) $office;
                $headers = ['Content-Type' => 'application/json'];
                $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
                $config = [
                    'headers' => $headers,
                    'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
                ];
                $endpoint = self::API_BRANCHES_ENDPOINT;

                // The fetch needs to be queued, too heavy for a web-request.
                Queue::push(
                    job: new FetchBranchesJob([
                        'config' => $config,
                        'headers' => $headers,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'office' => $office,
                    ]),
                    priority: 10,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }

    /**
     * @param object $office
     * @param string $kindId
     * @param string $method
     * @return Collection
     * @throws GuzzleException
     */
    public function fetchCodeByKind(object $office, string $kindId, string $method = 'GET'): Collection
    {
        $headers = ['Content-Type' => 'application/json'];
        $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_CODES_ENDPOINT;

        $queryParams = [
            'query' => [
                'language' => self::LANGUAGE_CODE,
                'kind' => $kindId,
            ]
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);

        $response = $client->request($method, $endpoint, $queryParams);
        $response = json_decode($response->getBody()->getContents());

        return collect($response->codes);
    }

    /**
     * @param object $office
     * @param string $method
     * @return Collection
     * @throws GuzzleException
     */
    public function fetchUserByID(object $office, string $method = 'GET'): Collection
    {
        $headers = ['Content-Type' => 'application/json'];
        $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_USERS_ENDPOINT;

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);

        $response = $client->request($method, $endpoint, []);
        $response = json_decode($response->getBody()->getContents());

        return collect($response->users);
    }

    /**
     * @param string $method
     * @return void
     */
    public function fetchVacancies(string $method = 'GET'): void
    {
        $offices = $this->settings->officeCodes ?? null;

        if(!empty($offices)) {
            foreach($offices as $office) {

                $office = (object) $office;
                $headers = ['Content-Type' => 'application/json'];
                $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
                $config = [
                    'headers' => $headers,
                    'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
                ];
                $endpoint = self::API_VACANCY_ENDPOINT;

                $queryParams = [
                    'query' => [
                        'jobChannel' => App::parseEnv($this->settings->pratoFlexJobChannel),
                    ]
                ];

                // The fetch needs to be queued, too heavy for a web-request.
                Queue::push(
                    job: new FetchVacanciesJob([
                        'config' => $config,
                        'headers' => $headers,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'params' => $queryParams,
                        'office' => $office,
                    ]),
                    priority: 30,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }

    /**
     * @param int $vacancyId
     * @param string $officeCode
     * @param bool $enabled
     * @param string $method
     * @return bool
     * @throws Throwable
     */
    public function fetchVacancy(int $vacancyId, string $officeCode, bool $enabled = true, string $method = 'GET'): bool
    {

        $offices = collect([]);
        foreach ($this->settings->officeCodes as $office) {
            $offices->push([
                'officeToken' => $office['officeToken'],
                'officeCode' => App::parseEnv($office['officeCode']),
            ]);
        }

        $atsOffice = (object) $offices->where('officeCode', $officeCode)->first();

        if($atsOffice) {
            $headers = ['Content-Type' => 'application/json'];
            $headers['Authorization']  = 'WB ' . App::parseEnv($atsOffice->officeToken);
            $config = [
                'headers' => $headers,
                'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
            ];
            $endpoint = self::API_VACANCY_ENDPOINT . '/' . $vacancyId;

            $queryParams = [
                'query' => [
                    'id' => $vacancyId,
                ]
            ];

            $client = Ats::$plugin->guzzleService->createGuzzleClient($config);

            try {
                $response = $client->request($method, $endpoint, $queryParams);
            } catch (GuzzleException $exception) {
                Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
                return false;
            }

            if($response->getStatusCode() === 200) {
                $response = json_decode($response->getBody()->getContents());

                $syncStatus = false;
                try {
                    Queue::push(
                        job: new VacancyJob([
                            'vacancyId' => $response->id,
                            'vacancy' => $response,
                            'enabled' => $enabled,
                            'office' => $atsOffice,
                        ]),
                        priority: 10,
                        ttr: 1000,
                        queue: Ats::$plugin->queue,
                    );
                    $syncStatus = true;
                } catch (Throwable $exception) {
                    Ats::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
                }

                return $syncStatus;
            }
        }
        return false;
    }
}
