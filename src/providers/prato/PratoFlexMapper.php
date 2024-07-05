<?php

namespace craftpulse\ats\providers\prato;

use Carbon\Carbon;

use Craft;
use craft\helpers\Queue;
use craftpulse\ats\Ats;

use craftpulse\ats\jobs\VacancyJob;
use craftpulse\ats\jobs\SectorJob;
use craftpulse\ats\jobs\OfficeJob;
use craftpulse\ats\jobs\CodeJob;
use craftpulse\ats\jobs\FunctionJob;

use craftpulse\ats\models\CodeModel;
use craftpulse\ats\models\FunctionModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\models\SectorModel;
use craftpulse\ats\models\VacancyModel;

use yii\base\Component;

class PratoFlexMapper extends Component
{
    public ?VacancyModel $vacancy = null;
    
    public function syncBranches($response, $office = null): void
    {
        if ($response->branches ?? null)
        {
            foreach ($response->branches as $key => $officeResponse) {

                $branch = new OfficeModel();

                $branch->branchId = $officeResponse->id;
                $branch->name = $officeResponse->name;

                Queue::push(
                    job: new OfficeJob([
                        'branchId' => $branch->branchId,
                        'branch' => $branch,
                        'office' => $office,
                    ]),
                    priority: 10,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }

    public function syncFunctions($response, $office = null): void
    {
        if ($response->functions ?? null)
        {
            foreach ($response->functions as $key => $functionResponse) {
                $function = new FunctionModel();

                $function->functionId = $functionResponse->id;

                Queue::push(
                    job: new FunctionJob([
                        'functionId' => $function->functionId,
                        'function' => $function,
                        'office' => $office,
                    ]),
                    priority: 20,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }

    public function syncSectors($response, $office = null): void
    {
        if ($response->sectors ?? null)
        {
            foreach ($response->sectors as $key => $sectorResponse) {
                $sector = new SectorModel();
                $sector->sectorId = $sectorResponse->id;
                $sector->title = $sectorResponse->name;

                Queue::push(
                    job: new SectorJob([
                        'sectorId' => $sector->sectorId,
                        'sector' => $sector,
                        'office' => $office,
                    ]),
                    priority: 20,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }

    public function syncCodes($response, $office = null, string $handle = null): void
    {
        if ($response->codes ?? null)
        {
            foreach ($response->codes as $key => $codeResponse) {
                $code = new CodeModel();
                $code->codeId = $codeResponse->id;
                $code->title = $codeResponse->description;

                Queue::push(
                    job: new CodeJob([
                        'handle' => $handle,
                        'codeId' => $code->codeId,
                        'code' => $code,
                        'office' => $office,
                    ]),
                    priority: 20,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }


    /**
     * @param $response
     * @param $office
     * @return void{
     * "orders": [
     * {
     * "addresscity": "string",
     * "addresscountryid": "string",
     * "addresshousenumber": "string",
     * "addressmailboxnumber": "string",
     * "addressstreet": "string",
     * "addresszip": 0,
     * "addresszipcode": "string",
     * "amount": 0,
     * "branchid": 0,
     * "brutowage": 0,
     * "brutowageinfo": "string",
     * "certificates": "string",
     * "channels": [
     * "string"
     * ],
     * "clientdepartmentid": 0,
     * "clientid": 0,
     * "clientname": "string",
     * "clientzip": "string",
     * "contracttypeid": "string",
     * "cycle": 0,
     * "datecreate": "2024-07-02T15:16:33.327Z",
     * "datemodify": "2024-07-02T15:16:33.327Z",
     * "duration": "string",
     * "education": "string",
     * "experience": "string",
     * "extra1": "string",
     * "extraid": "string",
     * "ftworkinghrsweek": 0,
     * "fulltimehours": 0,
     * "function": "string",
     * "functiongroupid": 0,
     * "functiongroupmainid": 0,
     * "functionid": 0,
     * "functionsafetyinfo": "string",
     * "id": 0,
     * "itskills": "string",
     * "kindid": "string",
     * "knowhow": "string",
     * "languagedescription": "string",
     * "languageid": "string",
     * "languageskill": "string",
     * "languageskills": [
     * {
     * "languagecode": "string",
     * "language": "string",
     * "speaking": 0,
     * "writing": 0,
     * "understanding": 0,
     * "ismothertongue": true,
     * "remark": "string",
     * "modified": "2024-07-02T15:16:33.327Z"
     * }
     * ],
     * "name": "string",
     * "offer": "string",
     * "partimehours": 0,
     * "permanentpossibleid": "string",
     * "placeofemployment": "string",
     * "placeofemploymentzipcode": {
     * "city": "string",
     * "community": {
     * "id": 0,
     * "validfrom": "2024-07-02T15:16:33.327Z",
     * "validuntil": "2024-07-02T15:16:33.327Z",
     * "datecreated": "2024-07-02T15:16:33.327Z",
     * "datemodified": "2024-07-02T15:16:33.327Z",
     * "kind": "AmountPersons",
     * "code": "string",
     * "languagecode": "string",
     * "descriptionshort": "string",
     * "cdkey": "string",
     * "dateannulation": "2024-07-02T15:16:33.327Z",
     * "description": "string",
     * "externalcode": "string",
     * "externalcode2": "string",
     * "externalcode3": "string",
     * "externalcodesuppl": "string",
     * "keyvalue": "string",
     * "kindraw": "string",
     * "usercreated": "string",
     * "usermodified": "string"
     * },
     * "communityid": 0,
     * "districtid": 0,
     * "id": 0,
     * "lookupcity": "string",
     * "merger": "string",
     * "officecode": "string",
     * "officecode2": "string",
     * "province": {
     * "id": 0,
     * "validfrom": "2024-07-02T15:16:33.327Z",
     * "validuntil": "2024-07-02T15:16:33.327Z",
     * "datecreated": "2024-07-02T15:16:33.327Z",
     * "datemodified": "2024-07-02T15:16:33.327Z",
     * "kind": "AmountPersons",
     * "code": "string",
     * "languagecode": "string",
     * "descriptionshort": "string",
     * "cdkey": "string",
     * "dateannulation": "2024-07-02T15:16:33.327Z",
     * "description": "string",
     * "externalcode": "string",
     * "externalcode2": "string",
     * "externalcode3": "string",
     * "externalcodesuppl": "string",
     * "keyvalue": "string",
     * "kindraw": "string",
     * "usercreated": "string",
     * "usermodified": "string"
     * },
     * "provinceid": "string",
     * "region": {
     * "id": 0,
     * "validfrom": "2024-07-02T15:16:33.327Z",
     * "validuntil": "2024-07-02T15:16:33.327Z",
     * "datecreated": "2024-07-02T15:16:33.327Z",
     * "datemodified": "2024-07-02T15:16:33.327Z",
     * "kind": "AmountPersons",
     * "code": "string",
     * "languagecode": "string",
     * "descriptionshort": "string",
     * "cdkey": "string",
     * "dateannulation": "2024-07-02T15:16:33.327Z",
     * "description": "string",
     * "externalcode": "string",
     * "externalcode2": "string",
     * "externalcode3": "string",
     * "externalcodesuppl": "string",
     * "keyvalue": "string",
     * "kindraw": "string",
     * "usercreated": "string",
     * "usermodified": "string"
     * },
     * "regionid": "string",
     * "zipid": "string"
     * },
     * "priority": "string",
     * "ptworkinghrsweek": 0,
     * "publicationend": "2024-07-02T15:16:33.327Z",
     * "publicationstart": "2024-07-02T15:16:33.327Z",
     * "regimes": [
     * "string"
     * ],
     * "region": "string",
     * "remark": "string",
     * "remunerationinfo": "string",
     * "requiredyearsofexperience": 0,
     * "rosterinfo": "string",
     * "salaryinfo": "string",
     * "sectiondescription": "string",
     * "sectionid": "string",
     * "sectordescription": "string",
     * "sectorid": "string",
     * "skills": "string",
     * "statusid": "string",
     * "taskandprofile": "string",
     * "userassignedid": 0,
     * "usercreatedid": 0,
     * "userid": 0,
     * "vdabdriverslicences": [
     * "string"
     * ],
     * "vdabeducations": [
     * "string"
     * ],
     * "vdablanguages": [
     * {
     * "competencyid": "string",
     * "level": "string"
     * }
     * ],
     * "vdabregions": [
     * "string"
     * ],
     * "workplacesafetyinfo": "string",
     * "workshifts": [
     * "string"
     * ],
     * "zipcoderaw": "string"
     * }
     * ]
     * }
     */
    public function syncVacancies($response): void
    {
        if ($response->orders ?? null) {
            foreach ($response->orders as $key => $vacancyResponse) {

                // @TODO - check if branchId exists in the system, if not skip the sync

                $expiryDate = new Carbon($vacancyResponse->publicationstart);
                $expiryDate->addMonths(3);

                $vacancyModel = new VacancyModel();

                $vacancyModel->vacancyId = $vacancyResponse->id;
                $vacancyModel->title = $vacancyResponse->name;
                $vacancyModel->dateCreated = new Carbon($vacancyResponse->publicationstart);
                $vacancyModel->expiryDate = $expiryDate;

                $vacancyModel->clientName = $vacancyResponse->clientname;
                $vacancyModel->clientId = $vacancyResponse->clientid;
                $vacancyModel->taskAndProfile = $vacancyResponse->taskandprofile;
                $vacancyModel->skills = $vacancyResponse->skills;
                $vacancyModel->education = $vacancyResponse->education;
                $vacancyModel->offer = $vacancyResponse->offer;
                $vacancyModel->requiredYearsOfExperience = $vacancyResponse->requiredyearsofexperience;
                $vacancyModel->amount = $vacancyResponse->amount;
                $vacancyModel->fulltimeHours = $vacancyResponse->fulltimehours;
                $vacancyModel->parttimeHours = $vacancyResponse->parttimehours ?? null;
                $vacancyModel->brutoWage = $vacancyResponse->brutowage;
                $vacancyModel->brutoWageInfo = $vacancyResponse->brutowageinfo;
                $vacancyModel->remark = $vacancyResponse->remark;
                $vacancyModel->extra = $vacancyResponse->extra1;
                $vacancyModel->branchId = $vacancyResponse->branchid;

                $vacancyModel->sectorId = $vacancyResponse->sectorid;
                $vacancyModel->officeId = Ats::$plugin->offices->getBranchById($vacancyResponse->branchid)->id;
                $vacancyModel->workshiftId = empty($vacancyResponse->workshifts) ? null : $vacancyResponse->workshifts[0];
                $vacancyModel->contractTypeId = $vacancyResponse->contracttypeid ?? null;
                $vacancyModel->regimeId = empty($vacancyResponse->regimes) ? null : $vacancyResponse->regimes[0];

                Queue::push(
                    job: new VacancyJob([
                        'vacancyId' => $vacancyModel->vacancyId,
                        'vacancy' => $vacancyModel,
                    ]),
                    priority: 30,
                    ttr: 1000,
                    queue: Ats::$plugin->queue,
                );
            }
        }
    }
}