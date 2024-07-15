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
    public ?int $provinceId = null;

    public ?string $street = null;
    public ?string $postCode = null;
    public ?string $city = null;
    public ?float $latitude = null;
    public ?float $longitude = null;

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}
