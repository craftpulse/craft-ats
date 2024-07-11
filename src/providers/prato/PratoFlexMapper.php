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

use craftpulse\ats\models\CodeModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\models\SectorModel;
use craftpulse\ats\models\VacancyModel;

use Illuminate\Support\Collection;

use yii\base\Component;

class PratoFlexMapper extends Component
{
    public ?VacancyModel $vacancy = null;

    public const COUNTRY = 'België';

    /**
     * @param $response
     * @param object|null $office
     * @return void
     */
    public function syncBranches($response, ?object $office = null): void
    {
        if ($response->branches ?? null)
        {
            foreach ($response->branches as $key => $officeResponse) {

                $branch = new OfficeModel();

                $branch->branchId = $officeResponse->id;
                $branch->name = $officeResponse->name;
                $branch->phone = $officeResponse->phone;
                $branch->email = $officeResponse->email;
                $branch->fax = $officeResponse->fax;
                if($office) {
                    $branch->officeCode = $office->officeCode;
                }

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

    /**
     * @param $response
     * @param object|null $office
     * @return void
     */
    public function syncSectors($response, ?object $office = null): void
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

    /**
     * @param $response
     * @param object|null $office
     * @param string|null $handle
     * @return void
     */
    public function syncCodes($response, ?object $office = null, string $handle = null): void
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
     * @param object|null $office
     * @return void
     */
    public function syncVacancies($response, ?object $office = null): void
    {
        $settings = Ats::$plugin->settings;

        if ($response->orders ?? null) {
            foreach ($response->orders as $key => $vacancyResponse) {

                $provider = new PratoFlexProvider();

                // @TODO - check if branchId exists in the system, if not skip the sync

                $expiryDate = new Carbon($vacancyResponse->publicationstart);
                $expiryDate->addMonths(3);

                $vacancyModel = new VacancyModel();

                if(!$vacancyResponse->contracttypeid == "" || !empty($vacancyResponse->regimes)) {
                    $vacancyModel->vacancyId = $vacancyResponse->id;
                    $vacancyModel->title = ucfirst($vacancyResponse->name);
                    $vacancyModel->postDate = new Carbon($vacancyResponse->publicationstart);
                    $vacancyModel->expiryDate = $expiryDate;

                    $vacancyModel->clientName = $vacancyResponse->clientname;
                    $vacancyModel->clientId = $vacancyResponse->clientid;
                    $vacancyModel->taskAndProfile = $vacancyResponse->taskandprofile;
                    $vacancyModel->skills = $vacancyResponse->skills;
                    $vacancyModel->education = $vacancyResponse->education;
                    $vacancyModel->offer = $vacancyResponse->offer;
                    $vacancyModel->requiredYearsOfExperience = $vacancyResponse->requiredyearsofexperience;
                    $vacancyModel->amount = $vacancyResponse->amount;
                    $vacancyModel->fulltimeHours = $vacancyResponse->fulltimehours ?? null;
                    $vacancyModel->parttimeHours = $vacancyResponse->parttimehours ?? null;
                    $vacancyModel->brutoWage = $vacancyResponse->brutowage;
                    $vacancyModel->brutoWageInfo = $vacancyResponse->brutowageinfo;
                    $vacancyModel->remark = $vacancyResponse->remark;
                    $vacancyModel->extra = $vacancyResponse->extra1;

                    $vacancyModel->officeId = Ats::$plugin->offices->getBranchById($vacancyResponse->branchid)->id;

                    // Workshifts don't always exist - can be null/nothing
                    if(!empty($vacancyResponse->workshifts)) {
                        $workshift = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['workshift']['kindId'])->where('id', $vacancyResponse->workshifts[0])->first()->description);
                        if($workshift != null || $workshift != '') {
                            $vacancyModel->workshiftId = (int)Ats::$plugin->codes->getCodeByTitle($workshift, $settings->shiftHandle)->id;
                        }
                    }

                    // Sectors always exists
                    if($vacancyResponse->sectorid != '') {
                        $sector = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['sector']['kindId'])->where('id', $vacancyResponse->sectorid)->first()->description);
                        if($sector != null || $sector != '') {
                            $vacancyModel->sectorId = (int)Ats::$plugin->codes->getCodeByTitle($sector, $settings->sectorHandle)->id;
                        }
                    }

                    // Contract Type always exists
                    if($vacancyResponse->contracttypeid != '') {
                        $contractType = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['contractType']['kindId'])->where('id', $vacancyResponse->contracttypeid)->first()->description);
                        if($contractType != null || $contractType != '') {
                            $vacancyModel->contractTypeId = (int)Ats::$plugin->codes->getCodeByTitle($contractType, $settings->contractTypeHandle)->id;
                        }
                    }

                    // Regime always exists
                    if(!empty($vacancyResponse->regimes[0])) {
                        $regime = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['regime']['kindId'])->where('id', $vacancyResponse->regimes[0])->first()->description);
                        if($regime != null || $regime != '') {
                            $vacancyModel->regimeId = (int)Ats::$plugin->codes->getCodeByTitle($regime, $settings->workRegimeHandle)->id;
                        }
                    }

                    // Set Location
                    $city = $vacancyResponse->placeofemploymentzipcode->city ?? null;
                    $country = self::COUNTRY;
                    $address ="{$vacancyResponse->placeofemployment} {$city},{$country}";

                    // Add place of employment (geomap it)
                    $coords = Ats::$plugin->mapbox->getGeoPoints($address);

                    $vacancyModel->latitude = $coords[0];
                    $vacancyModel->longitude = $coords[1];
                    $vacancyModel->city = $city ?? null;
                    $vacancyModel->postCode = $vacancyResponse->placeofemployment;

                    // Set and create user just like with our regimes, but yet another endpoint :)
                    $jobAdvisor = null;
                    if(!empty($vacancyResponse->userid)) {
                        $jobAdvisor = $provider->fetchUserById($office)->reject( fn($value) => (empty($value->name) || $value->name = "") )->where('id', $vacancyResponse->userid)->first();
                        if($jobAdvisor) {
                            $vacancyModel->jobAdvisorId = (int)Ats::$plugin->users->getUserById(collect($jobAdvisor), $settings->contactsHandle)->id;
                        }
                    }

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
}
