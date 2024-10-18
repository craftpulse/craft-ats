<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\ClientModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**addrepublipub
 * Office Service service
 */
class SyncOfficesService extends Component
{
    /**
     * @event BranchEvent
     */
    public const EVENT_BEFORE_SYNC_BRANCH = 'beforeSyncBranch';

    /**
     * @event BranchEvent
     */
    public const EVENT_AFTER_SYNC_BRANCH = 'afterSyncBranch';

    /**
     * @var null|object
     */
    public ?object $provider = null;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    /**
     * @param int $branchId
     * @return OfficeModel|null
     */
    public function getBranchByBranchId(int $branchId): ?OfficeModel
    {
        if (!$branchId) {
            return null;
        }

        $branchRecord = Entry::find()
            ->section(Ats::$plugin->settings->officeHandle)
            ->branchId($branchId)
            ->status(null)
            ->one();

        if ($branchRecord === null) {
            return null;
        }

        $branch = new OfficeModel();
        $branch->setAttributes($branchRecord->getAttributes(), false);
        $branch->branchId = $branchRecord->branchId;

        return $branch;
    }

    /**
     * @param string $branchId
     * @return string|null
     */
    public function getOfficeCodeByBranch(string $branchId): ?string
    {
        if (!$branchId) {
            return null;
        }

        $branchRecord = Entry::find()
            ->section(Ats::$plugin->settings->officeHandle)
            ->id($branchId)
            ->status(null)
            ->one();

        if ($branchRecord === null) {
            return null;
        }

        return $branchRecord->officeCode;
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function saveBranch(OfficeModel $branch): bool
    {
        if ($branch->validate() === false) {
            return false;
        }

        if ($branch->branchId) {
            $branchRecord = Entry::find()
                ->id($branch->branchId)
                ->status(null)
                ->one();

            if ($branchRecord === null) {
                // CREATE NEW
                $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->officeHandle);

                if ($section) {
                    $branchRecord = new Entry([
                        'sectionId' => $section->id
                    ]);
                }
            }

            $branchRecord->title = $branch->name;
            $branchRecord->branchId = $branch->branchId;
            $branchRecord->province = [$branch->provinceId ?? null];
            $branchRecord->latitude = $branch->latitude;
            $branchRecord->longitude = $branch->longitude;
            $branchRecord->city = $branch->city;
            $branchRecord->postCode = $branch->postCode;
            $branchRecord->addressLine1 = $branch->street;
            $branchRecord->officeCode = $branch->officeCode;

            $enabledForSites = [];
            foreach($branchRecord->getSupportedSites() as $site) {
                $enabledForSites[] = $site['siteId'];
            }
            $branchRecord->setEnabledForSite($enabledForSites);
            $branchRecord->enabled = true;

            $saved = Craft::$app->getElements()->saveElement($branchRecord);

            return $saved;
        }

        return false;
    }

    /**
     * @param callable|null $progressHandler
     * @param bool $queue
     * @return void
     */
    public function syncBranches(callable $progressHandler = null, bool $queue = true): void
    {
        $this->provider->fetchBranches();
    }
}
