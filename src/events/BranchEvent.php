<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;
use craftpulse\ats\models\OfficeModel;

class BranchEvent extends CancelableEvent
{
    /**
     * @var OfficeModel|null
     */
    public ?OfficeModel $branch = null;
}