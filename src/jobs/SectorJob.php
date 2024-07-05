<?php

namespace craftpulse\ats\jobs;

use Craft;
use craft\queue\BaseJob;
use craftpulse\ats\Ats;
use craftpulse\ats\events\SectorEvent;
use craftpulse\ats\services\SyncSectorsService;
use craftpulse\ats\models\SectorModel;
use yii\queue\RetryableJobInterface;

class SectorJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int
     */
    public int $sectorId;

    /**
     * @var object|null
     */
    public ?object $office;

    /**
     * @var SectorModel|null
     */
    public ?SectorModel $sector = null;

    /*public function init(): void
    {
        $this->batchSize = 11;

        parent::init();
    }*/

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return 1000;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < 20;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $sector = $this->getSector();

        if ($sector === null) {
            return;
        }

        $event = new SectorEvent([
            'sector' => $sector,
        ]);

        Ats::$plugin->sectors->trigger(SyncSectorsService::EVENT_BEFORE_SYNC_SECTOR, $event);
        Ats::$plugin->sectors->saveSector($this->sector);

        if (Ats::$plugin->sectors->hasEventHandlers(SyncSectorsService::EVENT_AFTER_SYNC_SECTOR)) {
            Ats::$plugin->sectors->trigger(SyncSectorsService::EVENT_AFTER_SYNC_SECTOR, new SectorEvent([
                'sector' => $sector,
            ]));
        }

        if (!$event->isValid) {
            return;
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('ats', "Syncing {$this->sector->title}");
    }

    private function getSector(): ?SectorModel {
        // Check if sector exists, if it exists map it to the existing one else create new
        $sector = Ats::$plugin->sectors->getSectorById($this->sectorId);

        if (!is_null($sector)) {
            $this->sector = $sector;
        }

        return $this->sector;
    }
}