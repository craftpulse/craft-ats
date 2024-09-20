<?php

namespace craftpulse\ats\models;

use craft\base\Model;

/**
 * Office model
 *
 * @property string $id
 * @property string $name
 * @property string $branchId
 * @property string|null $email
 * @property string|null $fax
 * @property string|null $phone
 * @property string|null $officeCode
 * @property int|null $provinceId
 * @property string|null $street
 * @property string|null $postCode
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
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
}
