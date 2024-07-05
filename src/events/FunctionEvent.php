<?php

namespace craftpulse\ats\events;

use craft\events\CancelableEvent;
use craftpulse\ats\models\FunctionModel;

class FunctionEvent extends CancelableEvent
{
    /**
     * @var FunctionModel|null
     */
    public ?FunctionModel $function = null;
}