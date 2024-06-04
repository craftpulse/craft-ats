<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use yii\base\Component;
use craftpulse\ats\Ats;

/**
 * Job Service service
 */
class JobService extends Component
{
    /**
     * Get the entry from jobs section by the ATS id field
     * @param int $jobId
     * @return Entry | null
     */
    public function getJobByJobId(int $jobId): ?Entry
    {
        return Entry::find()
            ->section('jobs')
            ->jobId($jobId)
            ->one();
    }

    /**
     * @param object $objJob
     * @return Entry|null
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function upsertJob(object $objJob): ?Entry
    {
//        try {
            $job = $this->getJobByJobId($objJob->id);

            if (is_null($job)) {
                $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->jobsHandle);

                if ($section) {
                    $job = new Entry([
                        'sectionId' => $section->id
                    ]);
                }
            }

            // job category fields
            $contractType = $this->upsertContractType($objJob->contracttype ?? null);
            $sector = $this->upsertSector($objJob->sector ?? null);
            $shifts = $this->upsertShift($objJob->jobconditions->shifts ?? []);
            $workRegimes = $this->upsertWorkRegimes($objJob->jobconditions->workregimes ?? []);
            $drivingLicenses = $this->upsertDrivingLicenses($objJob->jobrequirements->drivinglicenses ?? []);

            // job fields
            $job->jobId = $objJob->id;
            $job->title = $objJob->functionname;
            $job->prose = $objJob->function->description ?? null;
            $job->descriptionLevel1 = $objJob->function->descriptionlevel1 ?? null;
            $job->sectorsCategory = [$sector ?? null];
            $job->startDate = $objJob->startdate ?? null;
            $job->endDate = $objJob->enddate ?? null;
            $job->fulltimeHours = $objJob->jobconditions->fulltimehours ?? null;
            $job->parttimeHours = $objJob->jobconditions->parttimehours ?? null;
            $job->benefits = $this->_createList($objJob->jobconditions->extralegalbenefits ?? []);
            $job->offer = $objJob->jobconditions->offer ?? null;
            $job->tasksAndProfiles = $objJob->jobconditions->tasksandprofiles ?? null;
            $job->openings = $objJob->amount ?? null;
            $job->workRegimeCategory = $workRegimes ?? null;
            $job->contractTypeCategory = [$contractType ?? null];
            $job->shiftCategory = $shifts ?? null;
            $job->drivingLicenses = $drivingLicenses ?? null;
            $job->education = $objJob->jobrequirements->education ?? null;
            $job->requiredYearsOfExperience = $objJob->jobconditions->requiredyearsofexperience ?? null;
            $job->expertise = $objJob->jobrequirements->expertise ?? null;
            $job->certificates = $objJob->jobconditions->certificates ?? null;
            $job->skills = $objJob->jobconditions->skills ?? null;
            $job->extra = $objJob->jobconditions->extra ?? null;
            $job->wageMinimum = $objJob->jobconditions->brutowage ?? null;
            $job->wageInformation = $objJob->jobconditions->brutowageinformation ?? null;
            $job->wageDuration = $objJob->jobconditions->durationinformation ?? null;

            // @TODO: branchid -> office

            /** @TODO:
            * 1. Create category for location (title / postCode / latitude / longitude)
             * 2. fetch category by $objJob->zipcodeemployment
             * 3. if fetch returns null -> create one
             * 4. attach location to the location category field (which needs to be created)
             * */
            if ($job->postCode !== $objJob->zipcodeemployment ?? null && !is_null($objJob->zipcodeemployment)) {
                $mapboxService = new MapboxService();
                $location = $mapboxService->getAddress($objJob->zipcodeemployment . ' België');
                $coords = $mapboxService->getCoordsByLocation($location);

                $job->latitude = $coords[1] ?? '';
                $job->longitude = $coords[0] ?? '';
                $job->postCode = $objJob->zipcodeemployment ?? null;
            }

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
