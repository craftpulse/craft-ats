<?php
/**
 * Ats plugin for Craft CMS
 *
 * Sync vacancies coming from ATS Systems
 *
 * @link      https://craftpulse.com
 * @copyright Copyright (c) 2024 craftpulse
 */

namespace craftpulse\ats\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

class Settings extends Model
{
    /**
     * @var bool Should the vacancies automatically be synced when saving the settings of the plugin?
     */
    public bool $automaticallySyncVacancies = true;
}