<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craftpulse\ats\Ats;
use yii\base\Component;

/**
 * Clean Up Service service
 */
class CleanUpService extends Component
{
    public function removeUnusedJobCategories(): void
    {
        $this->cleanContractTypes();
        // job category fields
//        $contractType = $this->upsertContractType($jobModel->contractType);
//        $sector = $this->upsertSector($jobModel->sector);
//        $shifts = $this->upsertShift($jobModel->shifts);
//        $workRegimes = $this->upsertWorkRegimes($jobModel->workRegimes);
//        $drivingLicenses = $this->upsertDrivingLicenses($jobModel->drivingLicenses);
    }

    public function cleanContractTypes(): void
    {
        $contractTypes = Category::find()
            ->group(Ats::$plugin->settings->contractTypeHandle)
            ->all();

        $disableContractTypes = [];

        foreach($contractTypes as $contractType) {
            $job = Entry::find()
                ->section(Ats::$plugin->settings->jobsHandle)
                ->relatedTo($contractType)
                ->count();

            if ($job == 0) {
                array_push($disableContractTypes,$contractType->id);
            }
        }

        foreach($disableContractTypes as $disableContractType) {
            $category = Category::find()
                ->group(Ats::$plugin->settings->contractTypeHandle)
                ->id($disableContractType)
                ->one();

            $category->setEnabledForSite(false);
            Craft::$app->getElements()->saveElement($category);
        }
    }
}
