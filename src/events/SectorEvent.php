<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;
use craftpulse\ats\models\SectorModel;

class SectorEvent extends CancelableEvent
{
    /**
     * @var SectorModel|null
     */
    public ?SectorModel $sector = null;
}