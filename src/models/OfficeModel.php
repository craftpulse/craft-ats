<?php

namespace craftpulse\ats\models;

use Craft;
use craft\base\Model;

/**
 * Office Model model
 */
class OfficeModel extends Model
{
    public string $id = '';
    public string $name = '';
    public string $branchId = '';
    public ?string $email = null;
    public ?string $fax = null;
    public ?string $phone = null;
    public ?string $officeCode = null;

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}
