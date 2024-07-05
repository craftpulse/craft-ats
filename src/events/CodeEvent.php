<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;
use craftpulse\ats\models\CodeModel;

class CodeEvent extends CancelableEvent
{
    /**
     * @var CodeModel|null
     */
    public ?CodeModel $code = null;
}