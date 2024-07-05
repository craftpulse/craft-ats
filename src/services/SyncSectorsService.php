<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\SectorModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

class SyncSectorsService extends Component
{
    /**
     * @event SectorEvent
     */
    public const EVENT_BEFORE_SYNC_SECTOR = 'beforeSyncSector';

    /**
     * @event SectorEvent
     */
    public const EVENT_AFTER_SYNC_SECTOR = 'afterSyncSector';

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

    public function syncSectors(callable $progressHandler = null, bool $queue = true): void
    {
        $this->provider->fetchSectors();
    }

    public function getSectorById(int $sectorId): ?SectorModel
    {
        if(!$sectorId) {
            return null;
        }

        $sectorRecord = Category::find()
            ->group(Ats::$plugin->settings->sectorHandle)
            ->sectorId($sectorId)
            ->anyStatus()
            ->one();

        if($sectorRecord === null) {
            return null;
        }

        $sector = new SectorModel();
        $sector->setAttributes($sectorRecord->getAttributes(), false);

        return $sector;
    }

    /**
     * @throws ElementNotFoundException
     * @throws Throwable
     * @throws Exception
     */
    public function saveSector(SectorModel $sector): bool
    {
        if ($sector->validate() === false) {
            return false;
        }

        if ($sector->sectorId) {
            $sectorRecord = Category::find()
                ->group(Ats::$plugin->settings->sectorHandle)
                ->sectorId($sector->sectorId)
                ->anyStatus()
                ->one();

            if ($sectorRecord === null) {
                // CREATE NEW
                $category = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->sectorHandle);

                if ($category) {
                    $sectorRecord = new Category([
                        'groupId' => $category->id,
                    ]);
                }
            }

            $sectorRecord->title = $sector->title;
            $sectorRecord->sectorId = $sector->sectorId;
            $sectorRecord->setEnabledForSite($sectorRecord->getSupportedSites());
            $sectorRecord->enabled = true;

            return Craft::$app->getElements()->saveElement($sectorRecord);
        } else {
            // UPDATE
            var_dump('We update the sector');
        }

        return false;
    }
}