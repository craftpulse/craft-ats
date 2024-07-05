<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;
use craftpulse\ats\models\VacancyModel;

class VacancyEvent extends CancelableEvent
{
    /**
     * @var VacancyModel|null
     */
    public ?VacancyModel $vacancy = null;
}