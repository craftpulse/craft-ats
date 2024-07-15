<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;

class BranchEvent extends CancelableEvent
{
    /**
     * @var object|null
     */
    public ?object $branch = null;
}
