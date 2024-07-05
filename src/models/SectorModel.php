<?php

namespace craftpulse\ats\models;

use craft\base\Model;

/**
 * Sector model
 */
class SectorModel extends Model
{
    public string $id = '';
    public string $sectorId = '';
    public string $title = '';

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}