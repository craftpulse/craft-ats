<?php

namespace craftpulse\ats\providers\prato;

use Carbon\Carbon;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\helpers\App;
use craft\helpers\Queue;
use craftpulse\ats\Ats;

use craftpulse\ats\jobs\VacancyJob;
use craftpulse\ats\jobs\OfficeJob;

use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\models\VacancyModel;

use Illuminate\Support\Collection;

use Throwable;
use yii\base\Component;
use yii\base\Exception;

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
                Queue::push(
                    job: new OfficeJob([
                        'branchId' => $officeResponse->id,
                        'branch' => $officeResponse,
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
    public function syncVacancies($response, ?object $office = null): void
    {
        if ($response->orders ?? null) {
            foreach ($response->orders as $key => $vacancyResponse) {

                Queue::push(
                    job: new VacancyJob([
                        'vacancyId' => $vacancyResponse->id,
                        'vacancy' => $vacancyResponse,
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
     * @throws Exception
     * @throws ElementNotFoundException
     * @throws Throwable
     */
    public function syncOffice(object $officeResponse, ?object $office = null): void
    {
        if($office) {
            $settings = Ats::$plugin->settings;

            $provider = new PratoFlexProvider();

            $branchModel = new OfficeModel();

            $branchModel->branchId = $officeResponse->id;
            $branchModel->name = $officeResponse->name;
            $branchModel->phone = $officeResponse->phone;
            $branchModel->email = $officeResponse->email;
            $branchModel->fax = $officeResponse->fax;
            $branchModel->officeCode = App::parseEnv($office->officeCode);

            // Province always exists
            if (!empty($officeResponse->provinceid)) {
                $province = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['province']['kindId'])->where('id', $officeResponse->provinceid)->first()->description);
                if ($province != null || $province != '') {
                    $branchModel->provinceId = (int)Ats::$plugin->codes->getCodeByTitle($province, $settings->provincesHandle)->id;
                }
            }

            // Set Location
            $city = $officeResponse->city ?? null;
            $country = self::COUNTRY;
            $address = "{$officeResponse->street} {$city},{$country}";

            // Add place of employment (geomap it)
            $coords = Ats::$plugin->mapbox->getGeoPoints($address);

            $branchModel->latitude = $coords[1];
            $branchModel->longitude = $coords[0];
            $branchModel->city = $city ?? null;
            $branchModel->postCode = $officeResponse->zip;
            $branchModel->street = $officeResponse->street;

            Ats::$plugin->offices->saveBranch($branchModel);
        }
    }

    /**
     * @throws ElementNotFoundException
     * @throws Throwable
     * @throws Exception
     * @property-read $office The office object coming from the ATS Office Settings
     * @property-read $vacancyResponse The PratoFlex Response of the vacancies
     */
    public function syncVacancy(object $vacancyResponse, ?object $office = null): void
    {
        // Check if vacancy already exists in the system, if it does, do not sync it!
        $vacancy = Ats::$plugin->vacancies->getVacancyById($vacancyResponse->id);

        if (is_null($vacancy)) {
            if ($office) {
                $settings = Ats::$plugin->settings;

                $provider = new PratoFlexProvider();

                // @TODO - check if branchId exists in the system, if not skip the sync

                $publicationDate = new Carbon($vacancyResponse->publicationstart);
                $expiryDate = new Carbon($vacancyResponse->publicationstart);
                $expiryDate = $expiryDate->addMonths(3);

                $currentDate = Carbon::now();
                $expiryCheck = $currentDate->subMonths(3);

                if ($publicationDate->gt($expiryCheck)) {

                    $vacancyModel = new VacancyModel();

                    $officeId = Ats::$plugin->offices->getBranchByBranchId($vacancyResponse->branchid)?->id ?? null;

                    if (!$vacancyResponse->contracttypeid == "" || !empty($vacancyResponse->regimes || !is_null($officeId))) {
                        $vacancyModel->vacancyId = $vacancyResponse->id;
                        $vacancyModel->title = ucfirst($vacancyResponse->name);
                        $vacancyModel->postDate = $publicationDate;
                        $vacancyModel->expiryDate = $expiryDate;

                        $vacancyModel->officeCode = App::parseEnv($office->officeCode);

                        $vacancyModel->clientName = $vacancyResponse->clientname;
                        $vacancyModel->clientId = $vacancyResponse->clientid;
                        $vacancyModel->taskAndProfile = nl2br($vacancyResponse->taskandprofile);
                        $vacancyModel->skills = nl2br($vacancyResponse->skills);
                        $vacancyModel->education = nl2br($vacancyResponse->education);
                        $vacancyModel->offer = nl2br($vacancyResponse->offer);
                        $vacancyModel->requiredYearsOfExperience = $vacancyResponse->requiredyearsofexperience;
                        $vacancyModel->amount = $vacancyResponse->amount;
                        $vacancyModel->fulltimeHours = $vacancyResponse->fulltimehours ?? null;
                        $vacancyModel->parttimeHours = $vacancyResponse->parttimehours ?? null;
                        $vacancyModel->brutoWage = $vacancyResponse->brutowage;
                        $vacancyModel->brutoWageInfo = $vacancyResponse->brutowageinfo;
                        $vacancyModel->remark = nl2br($vacancyResponse->remark);
                        $vacancyModel->extra = $vacancyResponse->extra1;

                        $vacancyModel->officeId = $officeId;

                        // Workshifts don't always exist - can be null/nothing
                        if (!empty($vacancyResponse->workshifts)) {
                            $workshift = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['workshift']['kindId'])->where('id', $vacancyResponse->workshifts[0])->first()->description);
                            if ($workshift != null || $workshift != '') {
                                $vacancyModel->workshiftId = (int)Ats::$plugin->codes->getCodeByTitle($workshift, $settings->shiftHandle)->id;
                            }
                        }

                        // Sectors always exists
                        if ($vacancyResponse->sectorid != '') {
                            $sector = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['sector']['kindId'])->where('id', $vacancyResponse->sectorid)->first()->description);
                            if ($sector != null || $sector != '') {
                                $vacancyModel->sectorId = (int)Ats::$plugin->codes->getCodeByTitle($sector, $settings->sectorHandle)->id;
                            }
                        }

                        // Contract Type always exists
                        if (!empty($vacancyResponse->contracttypeid)) {
                            $contractType = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['contractType']['kindId'])->where('id', $vacancyResponse->contracttypeid)->first()->description);
                            if ($contractType != null || $contractType != '') {
                                $vacancyModel->contractTypeId = (int)Ats::$plugin->codes->getCodeByTitle($contractType, $settings->contractTypeHandle)->id;
                            }
                        }

                        // Regime always exists
                        if (!empty($vacancyResponse->regimes[0])) {
                            $regime = ucfirst($provider->fetchCodeByKind($office, PratoFlexProvider::KIND_IDS['regime']['kindId'])->where('id', $vacancyResponse->regimes[0])->first()->description);
                            if ($regime != null || $regime != '') {
                                $vacancyModel->regimeId = (int)Ats::$plugin->codes->getCodeByTitle($regime, $settings->workRegimeHandle)->id;
                            }
                        }

                        // Set Location
                        $city = $vacancyResponse->placeofemploymentzipcode->city ?? null;
                        $country = self::COUNTRY;
                        // Build the mapbox query
                        $address = "{$vacancyResponse->placeofemployment} {$city},{$country}";

                        // Add place of employment (geomap it)
                        $coords = Ats::$plugin->mapbox->getGeoPoints($address);

                        $vacancyModel->latitude = $coords[1];
                        $vacancyModel->longitude = $coords[0];
                        $vacancyModel->city = $city ?? null;
                        $vacancyModel->postCode = $vacancyResponse->placeofemployment;

                        // Set and create user just like with our regimes, but yet another endpoint :)
                        // This needs to be fixed :scream:
                        if (!empty($vacancyResponse->userid)) {
                            $jobAdvisor = $provider->fetchUserById($office)->reject(fn($value) => (empty($value->name)))->where('id', $vacancyResponse->userid)->first();
                            if ($jobAdvisor) {
                                $vacancyModel->jobAdvisorId = (int)Ats::$plugin->users->getUserById(collect($jobAdvisor), $settings->contactsHandle)->id;
                            }
                        }

                        Ats::$plugin->vacancies->saveVacancy($vacancyModel);
                    }
                }
            }

        }
    }
}
