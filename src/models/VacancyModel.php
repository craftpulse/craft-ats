<?php

namespace craftpulse\ats\models;

use craft\base\Model;
use DateTime;

/**
 * @property string $id
 * @property string $vacancyId
 * @property string $title
 * @property string $slug
 * @property string $clientId
 * @property DateTime $postDate
 * @property DateTime $expiryDate
 * @property bool $enabled
 * @property array $benefits
 * @property int $fulltimeHours
 * @property int $parttimeHours
 * @property int $requiredYearsOfExperience
 * @property string $functionName
 * @property string $certificates
 * @property int|string|null $officeId
 * @property int $sectorId
 * @property int $workshiftId
 * @property int $regimeId
 * @property int $contractTypeId
 * @property string $contractType
 * @property string $description
 * @property string $descriptionLevel1
 * @property string $education
 * @property string $endDate
 * @property string $expertise
 * @property string $extra
 * @property string $offer
 * @property string $sector
 * @property string $skills
 * @property string $startDate
 * @property string $taskAndProfile
 * @property string $remark
 * @property string $brutoWage
 * @property string $brutoWageInfo
 * @property string $amount
 * @property string $clientName
 * @property float $latitude
 * @property float $longitude
 * @property string $city
 * @property string $postCode
 * @property string $officeCode
 * @property int $jobAdvisorId
 */
class VacancyModel extends Model
{
    public string $id = '';
    public ?string $vacancyId = null;
    public string $title = '';
    public string $slug = '';

    public ?string $clientId = '';
    public ?DateTime $postDate = null;
    public ?DateTime $expiryDate = null;

    public bool $enabled = true;

    public ?array $benefits = null;
    public ?int $fulltimeHours = null;
    public ?int $parttimeHours = null;
    public ?int $requiredYearsOfExperience = null;

    public ?string $functionName = '';
    public ?string $certificates = null;

    public ?int $officeId = null;
    public ?int $sectorId = null;
    public ?int $workshiftId = null;
    public ?int $regimeId = null;
    public ?int $contractTypeId = null;

    public ?string $contractType = null;
    public ?string $description = null;
    public ?string $descriptionLevel1 = null;
    public ?string $education = null;
    public ?string $endDate = null;
    public ?string $expertise = null;
    public ?string $extra = null;
    public ?string $offer = null;
    public ?string $sector = null;
    public ?string $skills = null;
    public ?string $startDate = null;
    public ?string $taskAndProfile = null;

    public ?string $remark = null;
    public ?string $brutoWage = null;
    public ?string $brutoWageInfo = null;
    public ?string $amount = null;
    public ?string $clientName = null;

    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $city = null;
    public ?string $postCode = null;
    public ?string $officeCode = null;

    public ?int $jobAdvisorId = null;

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}
