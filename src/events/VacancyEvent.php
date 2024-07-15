<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;

class VacancyEvent extends CancelableEvent
{
    /**
     * @var object|null
     */
    public ?object $vacancy = null;
}
