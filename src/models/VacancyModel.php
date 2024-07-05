<?php

namespace craftpulse\ats\models;

use craft\base\Model;
use DateTime;

class VacancyModel extends Model
{
    public string $id;
    public string $vacancyId;
    public string $title;

    public ?string $clientId = '';
    public ?DateTime $dateCreated = null;
    public ?DateTime $expiryDate = null;


    public ?array $benefits = null;
    public ?array $drivingLicenses = null;
    public ?array $shifts = null;
    public ?array $workRegimes = null;
    public ?float $wageMaximum = null;
    public ?float $wageMinimum = null;
    public ?int $fulltimeHours = null;
    public ?int $openings = null;
    public ?int $parttimeHours = null;
    public ?int $requiredYearsOfExperience = null;

    public ?string $functionName = '';
    public ?string $branchId = '';
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
    public ?string $postCode = null;
    public ?string $sector = null;
    public ?string $skills = null;
    public ?string $startDate = null;
    public ?string $taskAndProfile = null;
    public ?string $wageDuration = null;
    public ?string $wageInformation = null;

    public ?string $remark = null;
    public ?string $brutoWage = null;
    public ?string $brutoWageInfo = null;
    public ?string $amount = null;
    public ?string $clientName = null;

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}