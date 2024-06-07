<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craftpulse\ats\models\JobModel;
use craftpulse\ats\providers\PratoFlexProvider;
use yii\base\Component;
use craftpulse\ats\Ats;

/**
 * Job Service service
 */
class JobService extends Component
{
    public function fetchJobs(): void
    {
        switch (Ats::$plugin->settings->atsProvider) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $jobs = $provider->fetchJobs();
        
        foreach($jobs as $job) {
            $this->upsertJob($job);
        }
    }



    /**
     * Get the entry from jobs section by the ATS id field
     * @param int $jobId
     * @return Entry | null
     */
    public function getJobByJobId(int $jobId): ?Entry
    {
        return Entry::find()
            ->section(Ats::$plugin->settings->jobsHandle)
            ->jobId($jobId)
            ->anyStatus()
            ->one();
    }

    /**
     * @param object $objJob
     * @return Entry|null
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function upsertJob(JobModel $jobModel): ?Entry
    {
//        try {
            $officeService = new OfficeService();
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
            $contact = $officeService->fetchContactByClientId($jobModel->clientId);

            // office
            // @TODO: create office from clientId
            $office = $officeService->fetchOffice($jobModel->officeId);
//            Craft::dd($office);

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
//        } catch (\Exception $e) {
//            Craft::error($e->getMessage(), __METHOD__);
//        }

        return null;
    }

    /**
     * Get contract type by the ATS contracttype field
     * @param string $title
     * @return bool
     */
    public function getContractTypeByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->contractTypeHandle)
            ->title($title)
            ->anyStatus()
            ->one();
    }

    /**
     * Upsert the contract type
     * @param string $title
     * @return int | null
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
     * Get sector by the ATS sector field
     * @param string $title
     * @return bool
     */
    public function getSectorByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->sectorHandle)
            ->title($title)
            ->anyStatus()
            ->one();
    }

    /**
     * Upsert the sector
     * @param string $title
     * @return int | null
     */
    public function upsertSector(string $title): ?int
    {
        if (is_null($title)) {
            return null;
        }

        // fetch category
        $category = $this->getSectorByTitle($title);

        // if category doesn't exist -> create
        if (is_null($category)) {
            $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->sectorHandle);

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
            ->anyStatus()
            ->one();
    }

    /**
     * Upsert the shifts
     * @param array $shifts
     * @return array
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
     * @return bool
     */
    public function getWorkRegimeByTitle(string $title): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->workRegimeHandle)
            ->title($title)
            ->anyStatus()
            ->one();
    }

    /**
     * Upsert the work regimes
     * @param array $shifts
     * @return array
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
     * @return bool
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
     * @param array $drivingLicenses
     * @return array
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
