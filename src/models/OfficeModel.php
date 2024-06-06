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
    public string $officeId = '';
    public string|null $addressLine1 = null;
    public string|null $addressLine2 = null;
    public string|null $postCode = null;
    public string|null $city = null;
    public string|null $latitude = null;
    public string|null $longitude = null;
    public string $taxNumber = '';
    public string|null $registrationNumber = null;
    public string|null $companyNumber = null;
    public int|null $province = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
