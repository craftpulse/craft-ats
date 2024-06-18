<?php

namespace craftpulse\ats\providers;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\Json;
use craftpulse\ats\models\ClientModel;
use craftpulse\ats\models\JobModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\services\JobService;
use yii\base\Component;
use craftpulse\ats\Ats;

/**
 * Job Service service
 */
class PratoFlexProvider extends Component
{
    /**
     * Fetches the jobs from PratoFlex and return the Job models as an array
     * @return array
     */
    public function fetchJobs(): array
    {
//        @TODO: Guzzle connection to fetch jobs
        $response = Json::decodeIfJson('{"data": [{"amount": 1,"applicationtype": "string","attemptselsewhere": 0,"branchid": 0,"clientcontactid": 0,"clientdepartmentid": 0,"clientid": 0,"coefficient": 0,"contracttype": "Flexi","enddate": "2024-06-14T07:04:25.006Z","function": {"description": "string","descriptionlevel1": "string","descriptionlevel2": "string","id": 1},"functionname": "Job from ATS mocking data","id": 1,"internalremarks": "string","jobconditions": {"brutowage": 3240,"brutowageinformation": "string","durationinformation": "string","extralegalbenefits": ["Maaltijdcheques van 7 euro per uur", "Fietsvergoeding"],"fulltimehours": 0,"offer": "string","parttimehours": 0,"remunerationinformation": "string","safetyinformationfunction": "string","safetyinformationworkspace": "string","shifts": ["Dagploeg","Weekendploeg"],"tasksandprofiles": "string","workingsystem": 0,"workregimes": ["Full-time","Part-time"],"workscheduleinformation": "string"},"jobrequirements": {"certificates": "string","drivinglicenses": ["B","C"],"education": "string","expertise": "string","extra": "string","itknowledge": ["string"],"linguisticknowledge": "string","requiredyearsofexperience": 0,"skills": "string"},"language": "string","name": "string","permanentemploymentremarks": "string","potentialpermanentemployment": true,"priority": "string","reason": "string","reasonremark": "string","sector": "Logistiek","startdate": "2024-05-14T07:04:25.006Z","status": "string","statusremark": "string","statute": "string","weekselsewhere": 0,"zipcodeemployment": "9000"}]}');

        $arrJobs = [];

        if ($response["data"] ?? null) {
            foreach($response["data"] as $job) {
                $jobModel = new JobModel();

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

    public function getSession(): ?Session {}
}