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
use Psr\Log\LogLevel;
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

    public function syncVacancy(int $vacancyId, string $officeCode, callable $progressHandler = null, bool $queue = true): void {
        Ats::$plugin->pratoProvider->fetchVacancy($vacancyId, $officeCode);
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function disableVacancy(int $vacancyId): void {
        $vacancy = $this->getVacancyEntryById($vacancyId);

        if($vacancy) {
            $vacancy->enabled = false;

            // Save the entry
            if(Craft::$app->elements->saveElement($vacancy)) {
                Ats::$plugin->log("Vacancy {$vacancy->title} with id {$vacancy->vacancyId} has been disabled");
            } else {
                Ats::$plugin->log("Failed to disable vacancy {$vacancy->title} with id {$vacancy->vacancyId}", [], LogLevel::ERROR);
            }
        }
    }

    public function getVacancyEntryById(int $vacancyId): ?Entry
    {
        if (!$vacancyId) {
            return null;
        }

        return Entry::find()
            ->section(Ats::$plugin->settings->jobsHandle)
            ->vacancyId($vacancyId)
            ->status(null)
            ->one();
    }

    public function getVacancyById(int $vacancyId): ?VacancyModel
    {
        if (!$vacancyId) {
            return null;
        }

        $vacancyRecord = Entry::find()
            ->section(Ats::$plugin->settings->jobsHandle)
            ->vacancyId($vacancyId)
            ->status(null)
            ->one();

        if ($vacancyRecord === null) {
            return null;
        }

        $vacancy = new VacancyModel();
        $vacancy->setAttributes($vacancyRecord->getAttributes(), false);

        return $vacancy;
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     */
    public function saveVacancy(VacancyModel $vacancy): bool
    {
        if ($vacancy->validate() === false) {
            return false;
        }

        if ($vacancy->vacancyId) {

            if ($vacancy->postDate ?? null) {

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
                $vacancyRecord->dateCreated = $vacancy->postDate;
                $vacancyRecord->postDate = $vacancy->postDate;
                $vacancyRecord->expiryDate = $vacancy->expiryDate;

                // Create custom slug branchid-vacancyid-slug
                $vacancyRecord->slug = $vacancy->slug;

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
                $vacancyRecord->officeCode = $vacancy->officeCode;

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

                // job advisor
                $vacancyRecord->jobAdvisor = [$vacancy->jobAdvisorId ?? null];

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
