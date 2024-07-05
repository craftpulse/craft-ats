<?php

namespace craftpulse\ats\models;

use Craft;
use craft\base\Model;

/**
 * Function model
 */
class FunctionModel extends Model
{
    public string $id = '';
    public string $functionId = '';
    public string $title = '';

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}