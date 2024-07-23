<?php

namespace craftpulse\ats\providers\prato;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;
use craftpulse\ats\Ats;
use craftpulse\ats\jobs\FetchBranchesJob;
use craftpulse\ats\jobs\FetchCodesJob;
use craftpulse\ats\jobs\FetchVacanciesJob;
use craftpulse\ats\models\SettingsModel;
use craftpulse\ats\models\VacancyModel;
use craftpulse\ats\models\OfficeModel;

use CURLFile;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Throwable;

use yii\base\Component;
use yii\base\Exception;

/**
 * Job Service service
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

    public const LANGUAGE_CODE = 'nl';

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

    private ?array $offices = null;
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
            } catch (ElementNotFoundException|Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            } catch (Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        return $response;
    }

    public function pushCvToUser(object $office, object $user, CURLFile $cv): void
    {
        // endpoint building and prepping data
        $headers = [
            'Authorization: WB ' . App::parseEnv($office->officeToken),
        ];
        $base_uri = App::parseEnv($this->settings->pratoFlexBaseUrl);
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT . '/' . $user->id . '/cvs';
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
     * Fetches the jobs from PratoFlex and return the Job models as an array
     * @param object $office
     * @param array $data
     * @param string $method
     * @return object
     * @throws GuzzleException
     */
    public function pushApplication(object $office, object $user, array $data, string $method = 'POST'): object
    {
        $headers = ['Content-Type' => 'application/json'];
        $headers['Authorization'] = 'WB ' . App::parseEnv($office->officeToken);
        $config = [
            'headers' => $headers,
            'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
        ];
        $endpoint = self::API_SUBSCRIPTIONS_ENDPOINT . '/' . $user->id . '/cvs';

        $body = [
            'body' => Json::encode($data),
        ];

        $client = Ats::$plugin->guzzleService->createGuzzleClient($config);
        $response = $client->request($method, $endpoint, $body);
        $response = json_decode($response->getBody()->getContents());

        if(!empty($response)) {
            try {
                Ats::$plugin->users->updateUser($response);
            } catch (ElementNotFoundException|Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            } catch (Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
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
     * @param string $method
     * @return void
     */
    public function fetchCodes(string $method = 'GET'): void
    {
        $offices = $this->settings->officeCodes ?? null;

        if (!is_null($offices)) {
            foreach($offices as $office) {

                $office = (object) $office;
                $headers = ['Content-Type' => 'application/json'];
                $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
                $config = [
                    'headers' => $headers,
                    'base_uri' => App::parseEnv($this->settings->pratoFlexBaseUrl),
                ];
                $endpoint = self::API_CODES_ENDPOINT;

                foreach(self::KIND_IDS as $code) {
                    $queryParams = [
                        'query' => [
                            'language' => self::LANGUAGE_CODE,
                            'kind' => $code['kindId'],
                        ]
                    ];

                    // The fetch needs to be queued
                    Queue::push(
                        job: new FetchCodesJob([
                            'config' => $config,
                            'headers' => $headers,
                            'endpoint' => $endpoint,
                            'params' => $queryParams,
                            'method' => $method,
                            'office' => $office,
                            'handle' => $code['section'],
                        ]),
                        priority: 20,
                        ttr: 1000,
                        queue: Ats::$plugin->queue,
                    );
                }
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

        if(!is_null($offices)) {
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

    public function fetchVacancyById(object $office, int $vacancyId, string $method = 'GET'): void
    {
        Craft::dd('later');
    }



























    public function fetchAllJobs(): void
    {
        $offices = Ats::$plugin->settings->officeCodes ?? null;
        $settings = Ats::$plugin->settings;

        if(!is_null($offices)) {
            foreach($offices as $office) {

                $office = (object) $office;
                $headers = ['Content-Type' => 'application/json'];
                $headers['Authorization']  = 'WB ' . App::parseEnv($office->officeToken);
                $config = [
                    'headers' => $headers,
                    'base_uri' => App::parseEnv($settings->pratoFlexBaseUrl),
                ];
                $params = [
                    'jobChannel' => App::parseEnv($settings->pratoFlexJobChannel),
                ];
                $endpoint = App::parseEnv(API_BRANCHES_ENDPOINT);

                Ats::$plugin->guzzleService->createGuzzleClient($headers, $config, $params, $endpoint);
            }
        }
    }

    public function fetchJobs(): array
    {
//       @TODO: Guzzle connection to fetch jobs
        $response = Json::decodeIfJson('{"data": [{"amount": 1,"applicationtype": "string","attemptselsewhere": 0,"branchid": 0,"clientcontactid": 0,"clientdepartmentid": 0,"clientid": 0,"coefficient": 0,"contracttype": "Flexi","enddate": "2024-06-14T07:04:25.006Z","function": {"description": "string","descriptionlevel1": "string","descriptionlevel2": "string","id": 1},"functionname": "Job from ATS mocking data","id": 1,"internalremarks": "string","jobconditions": {"brutowage": 3240,"brutowageinformation": "string","durationinformation": "string","extralegalbenefits": ["Maaltijdcheques van 7 euro per uur", "Fietsvergoeding"],"fulltimehours": 0,"offer": "string","parttimehours": 0,"remunerationinformation": "string","safetyinformationfunction": "string","safetyinformationworkspace": "string","shifts": ["Dagploeg","Weekendploeg"],"tasksandprofiles": "string","workingsystem": 0,"workregimes": ["Full-time","Part-time"],"workscheduleinformation": "string"},"jobrequirements": {"certificates": "string","drivinglicenses": ["B","C"],"education": "string","expertise": "string","extra": "string","itknowledge": ["string"],"linguisticknowledge": "string","requiredyearsofexperience": 0,"skills": "string"},"language": "string","name": "string","permanentemploymentremarks": "string","potentialpermanentemployment": true,"priority": "string","reason": "string","reasonremark": "string","sector": "Logistiek","startdate": "2024-05-14T07:04:25.006Z","status": "string","statusremark": "string","statute": "string","weekselsewhere": 0,"zipcodeemployment": "9000"}]}');

        $arrJobs = [];

        if ($response["data"] ?? null) {
            foreach($response["data"] as $job) {
                $jobModel = new VacancyModel();

                $jobModel->id = $job['id'];
                $jobModel->clientId = $job['clientid'];
                $jobModel->officeId = $job['branchid'];
                $jobModel->postCode = $job['zipcodeemployment'];
                $jobModel->functionName = $job['functionname'];
                $jobModel->description = $job['function']['description'] ?? null;
                $jobModel->descriptionLevel1 = $job['function']['descriptionlevel1'] ?? null;
                $jobModel->sector = $job['sector'] ?? null;
                $jobModel->startDate = $job['startdate'] ?? null;
                $jobModel->endDate = $job['enddate'] ?? null;
                $jobModel->fulltimeHours = $job['jobconditions']['fulltimehours'] ?? null;
                $jobModel->parttimeHours = $job['jobconditions']['parttimehours'] ?? null;
                $jobModel->benefits = $job['jobconditions']['extralegalbenefits'] ?? [];
                $jobModel->offer = $job['jobconditions']['offer'] ?? null;
                $jobModel->tasksAndProfiles = $job['jobconditions']['tasksandprofiles'] ?? null;
                $jobModel->openings = $job['amount'] ?? null;
                $jobModel->workRegimes = $job['jobconditions']['workregimes'] ?? null;
                $jobModel->contractType = $job['contracttype'] ?? null;
                $jobModel->shifts = $job['jobconditions']['shifts'] ?? null;
                $jobModel->drivingLicenses = $job['jobrequirements']['drivinglicenses'] ?? [];
                $jobModel->education = $job['jobrequirements']['education'] ?? null;
                $jobModel->requiredYearsOfExperience = $job['jobconditions']['requiredyearsofexperience'] ?? null;
                $jobModel->expertise = $job['jobrequirements']['expertise'] ?? null;
                $jobModel->certificates = $job['jobconditions']['certificates'] ?? null;
                $jobModel->skills = $job['jobconditions']['skills'] ?? null;
                $jobModel->extra = $job['jobconditions']['extra'] ?? null;
                $jobModel->wageMinimum = $job['jobconditions']['brutowage'] ?? null;
                $jobModel->wageInformation = $job['jobconditions']['brutowageinformation'] ?? null;
                $jobModel->wageDuration = $job['jobconditions']['durationinformation'] ?? null;

                array_push($arrJobs, $jobModel);
            }
        }

        return $arrJobs;
    }

    public function fetchOffice(string $branchId): ?OfficeModel
    {
        //@TODO: Guzzle connection to fetch the client information based on the branchId
        $response = Json::decodeIfJson('{"data": {
          "city": "Gent",
          "companyid": 1,
          "companynumber": "98968645",
          "country": 0,
          "id": 3,
          "name": "Kantoor Gent",
          "registrationnumber": "123456",
          "street": "Brabantdam 2",
          "taxnumber": "BE8787878787"
        }}');

        $officeModel = new OfficeModel();

        if ($response["data"] ?? null) {
            $officeModel->id = $response["data"]["id"];
            $officeModel->name = $response["data"]["name"];
            $officeModel->officeId = $response["data"]["companyid"];
            $officeModel->addressLine1 = $response["data"]["street"];
            $officeModel->city = $response["data"]["city"];
            $officeModel->taxNumber = $response["data"]["taxnumber"];
            $officeModel->registrationNumber = $response["data"]["registrationnumber"];
            $officeModel->companyNumber = $response["data"]["companynumber"];

            return $officeModel;
        }

        return null;
    }

    public function fetchContactByCompanyNumber(string $companyNumber): ?ClientModel
    {
        //@TODO: Guzzle connection to fetch the client information based on the compnay number
        $response = Json::decodeIfJson('{"data": [{
            "accountancycode": "string",
            "addresses": [
                {
                    "city": "string",
                    "country": 0,
                    "housenumber": "string",
                    "id": 0,
                    "mailboxnumber": "string",
                    "name": "string",
                    "street": "string",
                    "terrain": "string",
                    "type": 0,
                    "zip": 0,
                    "zipcode": "string"
                }
            ],
            "branch": 0,
            "communications": [
                {
                    "id": 1,
                    "type": "phone",
                    "value": "+32498664277"
                },
                {
                    "id": 2,
                    "type": "email",
                    "value": "stefanie.gevaert@pau.be"
                },
                {
                    "id": 3,
                    "type": "whatsapp",
                    "value": "+32498664277"
                },
                {
                    "id": 4,
                    "type": "X",
                    "value": "@cookie10codes"
                }
            ],
            "companynumber": "string",
            "contacts": [
                {
                    "active": true,
                    "birthdate": "2024-05-14T08:03:27.017Z",
                    "communications": [
                        {
                            "id": 1,
                            "type": "phone",
                            "value": "+32498664277"
                        },
                        {
                            "id": 2,
                            "type": "email",
                            "value": "stefanie.gevaert@pau.be"
                        },
                        {
                            "id": 3,
                            "type": "whatsapp",
                            "value": "+32498664277"
                        }
                    ],
                    "externalid": "string",
                    "firstname": "string",
                    "functiondescription": "string",
                    "gender": "string",
                    "id": 0,
                    "info": "string",
                    "isaccountant": true,
                    "isdefault": true,
                    "isrecipientreminders": true,
                    "issafetyadvisor": true,
                    "language": "string",
                    "name": "string",
                    "position": "string"
                }
            ],
            "deliverymethodtypeemploymentcontracts": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "deliverymethodtypeinvoices": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "deliverymethodtypeperformancesheets": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "externalid": "string",
            "financialrating": "string",
            "homepage": "string",
            "id": 0,
            "info": "string",
            "inss": "string",
            "invoicesystem": "string",
            "language": "string",
            "legalentity": "string",
            "name": "Stefanie Gevaert",
            "number": 0,
            "numberofemployees": "string",
            "socialsecuritynumber": "string",
            "spocs": [0],
            "taxationtype": "string",
            "taxnumber": "string",
            "taxobligatory": true,
            "termsofpayment": "string",
            "type": "string"
        }] }');

        if ($response["data"][0] ?? null) {
            $client = $response["data"][0];

            $clientModel = new ClientModel();

            $clientModel->id = $client['id'];
            $clientModel->branchId = $client['branch'] ?? null;
            $clientModel->name = $client['name'] ?? null;
            $clientModel->inss = $client['inss'] ?? null;
            $clientModel->info = $client['info'] ?? null;
            $clientModel->communications = $client['communications'] ?? null;

            return $clientModel;
        }

        return null;
    }

    public function fetchContactByClientId(string $clientId): ?ClientModel
    {
        //@TODO: Guzzle connection to fetch the client information based on the clientId
        $response = Json::decodeIfJson('{"data": [{
            "accountancycode": "string",
            "addresses": [
                {
                    "city": "string",
                    "country": 0,
                    "housenumber": "string",
                    "id": 0,
                    "mailboxnumber": "string",
                    "name": "string",
                    "street": "string",
                    "terrain": "string",
                    "type": 0,
                    "zip": 0,
                    "zipcode": "string"
                }
            ],
            "branch": 0,
            "communications": [
                {
                    "id": 1,
                    "type": "phone",
                    "value": "+333"
                }
            ],
            "companynumber": "string",
            "contacts": [
                {
                    "active": true,
                    "birthdate": "2024-05-14T08:03:27.017Z",
                    "communications": [
                        {
                            "id": 1,
                            "type": "phone",
                            "value": "+32498664277"
                        },
                        {
                            "id": 2,
                            "type": "email",
                            "value": "stefanie.gevaert@pau.be"
                        },
                        {
                            "id": 3,
                            "type": "whatsapp",
                            "value": "+32498664277"
                        }
                    ],
                    "externalid": "string",
                    "firstname": "string",
                    "functiondescription": "string",
                    "gender": "string",
                    "id": 0,
                    "info": "string",
                    "isaccountant": true,
                    "isdefault": true,
                    "isrecipientreminders": true,
                    "issafetyadvisor": true,
                    "language": "string",
                    "name": "string",
                    "position": "string"
                }
            ],
            "deliverymethodtypeemploymentcontracts": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "deliverymethodtypeinvoices": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "deliverymethodtypeperformancesheets": {
                "deliverymethodtype": "speos",
                "mailcc": [0],
                "mailto": 0
            },
            "externalid": "string",
            "financialrating": "string",
            "homepage": "string",
            "id": 0,
            "info": "string",
            "inss": "stringgg",
            "invoicesystem": "string",
            "language": "string",
            "legalentity": "string",
            "name": "Michael Thomas",
            "number": 0,
            "numberofemployees": "string",
            "socialsecuritynumber": "string",
            "spocs": [0],
            "taxationtype": "string",
            "taxnumber": "string",
            "taxobligatory": true,
            "termsofpayment": "string",
            "type": "string"
        }] }');

        if ($response["data"][0] ?? null) {
            $client = $response["data"][0];

            $clientModel = new ClientModel();

            $clientModel->id = $client['id'];
            $clientModel->branchId = $client['branch'] ?? null;
            $clientModel->name = $client['name'] ?? null;
            $clientModel->inss = $client['inss'] ?? null;
            $clientModel->info = $client['info'] ?? null;
            $clientModel->communications = $client['communications'] ?? null;

            return $clientModel;
        }

        return null;
    }
}
