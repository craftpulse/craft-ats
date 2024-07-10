<?php

namespace craftpulse\ats\services;

use Carbon\Carbon;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\helpers\Logger;
use craftpulse\ats\models\VacancyModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use craftpulse\ats\Ats;
use yii\base\Exception;

/**
 * Job Service service
 */
class SyncVacanciesService extends Component
{

    public const EVENT_BEFORE_SYNC_VACANCY = 'beforeSyncVacancy';

    /**
     * @event BranchEvent
     */
    public const EVENT_AFTER_SYNC_VACANCY = 'afterSyncVacancy';

    /**
     * @var null|object
     */
    public ?object $provider = null;

    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    public function syncVacancies(callable $progressHandler = null, bool $queue = true): void {
        Ats::$plugin->pratoProvider->fetchVacancies();
    }

    public function getVacancyById(int $vacancyId): ?VacancyModel
    {
        if (!$vacancyId) {
            return null;
        }

        $vacancyRecord = Entry::find()
            ->section(Ats::$plugin->settings->jobsHandle)
            ->vacancyId($vacancyId)
            ->anyStatus()
            ->one();

        if ($vacancyRecord === null) {
            return null;
        }

        $vacancy = new VacancyModel();
        $vacancy->setAttributes($vacancyRecord->getAttributes(), false);

        return $vacancy;
    }

    public function saveVacancy(VacancyModel $vacancy): bool
    {
        if ($vacancy->validate() === false) {
            return false;
        }

        if ($vacancy->vacancyId) {

            if ($vacancy->postDate ?? null)
            {
                $publicationDate = new Carbon($vacancy->postDate);
                $dateLimit = new Carbon();
                $dateLimit->subMonths(3);

                if ($dateLimit > $publicationDate) {
                    $importVacancy = false;
                } else {
                    $importVacancy = true;
                }
            } else {
                $importVacancy = true;
            }

            if($importVacancy) {
                $vacancyRecord = Entry::find()
                    ->id($vacancy->vacancyId)
                    ->status(null)
                    ->one();

                if ($vacancyRecord === null) {
                    // CREATE NEW
                    $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->jobsHandle);

                    if ($section) {
                        $vacancyRecord = new Entry([
                            'sectionId' => $section->id
                        ]);
                    }
                } else {
                    // UPDATE
                    var_dump('We update our jobby');
                }

                $vacancyRecord->title = $vacancy->title;
                $vacancyRecord->vacancyId = $vacancy->vacancyId;
                $vacancyRecord->dateCreated = $vacancy->dateCreated;
                $vacancyRecord->postDate = $vacancy->dateCreated;
                $vacancyRecord->expiryDate = $vacancy->expiryDate;

                $vacancyRecord->clientName = $vacancy->clientName;
                $vacancyRecord->tasksAndProfiles = $vacancy->taskAndProfile;
                $vacancyRecord->skills = $vacancy->skills;
                $vacancyRecord->education = $vacancy->education;
                $vacancyRecord->offer = $vacancy->offer;
                $vacancyRecord->requiredYearsOfExperience = $vacancy->requiredYearsOfExperience;
                $vacancyRecord->amount = $vacancy->amount;
                $vacancyRecord->fulltimeHours = $vacancy->fulltimeHours;
                $vacancyRecord->parttimeHours = $vacancy->parttimeHours;
                $vacancyRecord->brutoWage = $vacancy->brutoWage;
                $vacancyRecord->brutoWageInfo = $vacancy->brutoWageInfo;
                $vacancyRecord->remark = $vacancy->remark;
                $vacancyRecord->office = [$vacancy->officeId ?? null];

                // job category fields
                $vacancyRecord->sectorsCategory = [$vacancy->sectorId ?? null];
                $vacancyRecord->contractTypeCategory = [$vacancy->contractTypeId ?? null];
                $vacancyRecord->workRegimeCategory = [$vacancy->regimeId ?? null];
                $vacancyRecord->shiftCategory = [$vacancy->workshiftId ?? null];

                // location
                $vacancyRecord->postCode = $vacancy->postCode;
                $vacancyRecord->city = $vacancy->city;
                $vacancyRecord->latitude = $vacancy->latitude;
                $vacancyRecord->longitude = $vacancy->longitude;
                //$drivingLicenses = $this->upsertDrivingLicenses($vacancy->drivingLicenses);

                $indeedCode = null;

                if (str_starts_with($vacancy->extra, '#')) {
                    $indeedCode = $vacancy->extra;
                }

                $vacancyRecord->extra = $indeedCode;

                $enabledForSites = [];
                foreach ($vacancyRecord->getSupportedSites() as $site) {
                    $enabledForSites[] = $site['siteId'];
                }
                $vacancyRecord->setEnabledForSite($enabledForSites);
                $vacancyRecord->enabled = true;

                return Craft::$app->getElements()->saveElement($vacancyRecord);
            } else {
                return false;
            }
        }

        return false;
    }


    /**
     * @param object $objJob
     * @return Entry|null
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function upsertJob(VacancyModel $jobModel): ?Entry
    {
        try {
            $syncOffices = new SyncOfficesService();
            $locationService = new LocationService();

            $job = $this->getJobByJobId($jobModel->id);

            if (is_null($job)) {
                $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->jobsHandle);

                if ($section) {
                    $job = new Entry([
                        'sectionId' => $section->id
                    ]);
                }
            }

            // job category fields
            $contractType = $this->upsertContractType($jobModel->contractType);
            $sector = $this->upsertSector($jobModel->sector);
            $shifts = $this->upsertShift($jobModel->shifts);
            $workRegimes = $this->upsertWorkRegimes($jobModel->workRegimes);
            $drivingLicenses = $this->upsertDrivingLicenses($jobModel->drivingLicenses);

            // location
            $place = $locationService->upsertPlace($jobModel->postCode);

            // contact
            $contact = $syncOffices->fetchContactByClientId($jobModel->clientId);

            // office
            // @TODO: create office from clientId
            // $office = $offices->fetchOffice($jobModel->officeId);
            // Craft::dd($office);

            // job fields
            $job->jobId = $jobModel->id;
            $job->clientId = $jobModel->clientId;
            $job->branchId = $jobModel->officeId;
            $job->title = $jobModel->functionName;
            $job->prose = $jobModel->description;
            $job->descriptionLevel1 = $jobModel->descriptionLevel1;
            $job->sectorsCategory = [$sector];
            $job->placeCategory = [$place];
            $job->startDate = $jobModel->startDate;
            $job->endDate = $jobModel->endDate;
            $job->expiryDate = $jobModel->endDate ? new \DateTime($jobModel->endDate) : null;
            $job->fulltimeHours = $jobModel->fulltimeHours;
            $job->parttimeHours = $jobModel->parttimeHours;
            $job->benefits = $this->_createList($jobModel->benefits);
            $job->offer = $jobModel->offer;
            $job->tasksAndProfiles = $jobModel->tasksAndProfiles;
            $job->openings = $jobModel->openings;
            $job->workRegimeCategory = $workRegimes;
            $job->contractTypeCategory = [$contractType];
            $job->shiftCategory = $shifts;
            $job->drivingLicenses = $drivingLicenses;
            $job->education = $jobModel->education;
            $job->requiredYearsOfExperience = $jobModel->requiredYearsOfExperience;
            $job->expertise = $jobModel->expertise;
            $job->certificates = $jobModel->certificates;
            $job->skills = $jobModel->skills;
            $job->extra = $jobModel->extra;
            $job->wageMinimum = $jobModel->wageMinimum;
            $job->wageInformation = $jobModel->wageInformation;
            $job->wageDuration = $jobModel->wageDuration;
            $job->jobAdvisor = [$contact->id ?? null];
            $job->office = [$office->id ?? null];

            $enabledForSites = [];
            foreach($job->getSupportedSites() as $site) {
                array_push($enabledForSites, $site['siteId']);
            }
            $job->setEnabledForSite($enabledForSites);
            $job->enabled = true;

            // save element
            $saved = Craft::$app->getElements()->saveElement($job);
            // return category
            return $saved ? $job : null;
        } catch (\Exception $e) {
            $logger = new Logger();
            $logger->stdout(PHP_EOL, $logger::RESET);
            $logger->stdout($e->getMessage() . PHP_EOL, $logger::FG_RED);
            Craft::error($e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * Get contract type by the ATS contract type field
     * @param string $title
     * @return Category|null
     */
    public function getContractTypeByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->contractTypeHandle)
            ->title($title)
            ->status(null)
            ->one();
    }

    /**
     * Upsert the contract type
     * @param string|null $title
     * @return int | null
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function upsertContractType(?string $title): ?int
    {
        if (is_null($title)) {
            return null;
        }

        // fetch category
        $category = $this->getContractTypeByTitle($title);

        // if category doesn't exist -> create
        if (is_null($category)) {
            $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->contractTypeHandle);

            if ($categoryGroup) {
                $category = new Category([
                    'groupId' => $categoryGroup->id
                ]);
            }
        }

        if (!is_null($category)) {
            // save category fields
            $category->title = $title;

            $category->setEnabledForSite($category->getSupportedSites());

            // save element
            $saved = Craft::$app->getElements()->saveElement($category);

            // return category
            return $saved ? $category->id : null;
        }

        return null;
    }

    /**
     * Get shift by the ATS shift field
     * @param string $title
     * @return bool
     */
    public function getShiftByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->shiftHandle)
            ->title($title)
            ->status(null)
            ->one();
    }

    /**
     * Upsert the shifts
     * @param array|null $shifts
     * @return array|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function upsertShift(?array $shifts): ?array
    {
        if (is_null($shifts)) {
            return null;
        }

        $arrCategories = [];

        foreach($shifts as $shift) {
            // fetch category
            $category = $this->getShiftByTitle($shift);

            // if category doesn't exist -> create
            if (is_null($category)) {
                $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->shiftHandle);

                if ($categoryGroup) {
                    $category = new Category([
                        'groupId' => $categoryGroup->id
                    ]);
                }
            }

            if (!is_null($category)) {
                // save category fields
                $category->title = $shift;

                $category->setEnabledForSite($category->getSupportedSites());

                // save element
                $saved = Craft::$app->getElements()->saveElement($category);

                // return category
                $saved ? array_push($arrCategories, $category->id) : null;
            }
        }

        return $arrCategories;
    }

    /**
     * Get work regime by the ATS work regime field
     * @param string $title
     * @return Category|null
     */
    public function getWorkRegimeByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->workRegimeHandle)
            ->title($title)
            ->status(null)
            ->one();
    }

    /**
     * Upsert the work regimes
     * @param array|null $workRegimes
     * @return array|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function upsertWorkRegimes(?array $workRegimes): ?array
    {
        if (is_null($workRegimes)) {
            return null;
        }

        $arrCategories = [];

        foreach($workRegimes as $workRegime) {
            // fetch category
            $category = $this->getWorkRegimeByTitle($workRegime);

            // if category doesn't exist -> create
            if (is_null($category)) {
                $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->workRegimeHandle);

                if ($categoryGroup) {
                    $category = new Category([
                        'groupId' => $categoryGroup->id
                    ]);
                }
            }

            if (!is_null($category)) {
                // save category fields
                $category->title = $workRegime;

                $category->setEnabledForSite($category->getSupportedSites());

                // save element
                $saved = Craft::$app->getElements()->saveElement($category);

                // return category
                $saved ? array_push($arrCategories, $category->id) : null;
            }
        }

        return $arrCategories;
    }

    /**
     * Get driving license by the ATS driving license field
     * @param string $title
     * @return Category|null
     */
    public function getDrivingLicenseByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->drivingLicenseHandle)
            ->title($title)
            ->anyStatus()
            ->one();
    }

    /**
     * Upsert the driving licenses
     * @param array|null $drivingLicenses
     * @return array|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function upsertDrivingLicenses(?array $drivingLicenses): ?array
    {
        if (is_null($drivingLicenses)) {
            return null;
        }

        $arrCategories = [];

        foreach($drivingLicenses as $license) {
            // fetch category
            $category = $this->getDrivingLicenseByTitle($license);

            // if category doesn't exist -> create
            if (is_null($category)) {
                $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->drivingLicenseHandle);

                if ($categoryGroup) {
                    $category = new Category([
                        'groupId' => $categoryGroup->id
                    ]);
                }
            }

            if (!is_null($category)) {
                // save category fields
                $category->title = $license;

                $category->setEnabledForSite($category->getSupportedSites());

                // save element
                $saved = Craft::$app->getElements()->saveElement($category);

                // return category
                $saved ? array_push($arrCategories, $category->id) : null;
            }
        }

        return $arrCategories;
    }

    /**
     * Create a string with HTML based on an array with strings
     * @param array $items
     */
    private function _createList(array $items): string
    {
        $list = '<ul>';

        foreach($items as $item) {
            $list .= '<li>'.$item.'</li>';
        }

        $list .= '</ul>';

        return $list;
    }
}
