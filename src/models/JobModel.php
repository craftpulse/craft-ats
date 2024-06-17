<?php

namespace craftpulse\ats\models;

use craft\base\Model;

class JobModel extends Model
{
    public string $clientId = '';
    public array|null $benefits = null;
    public array|null $drivingLicenses = null;
    public array|null $shifts = null;
    public array|null $workRegimes = null;
    public float|null $wageMaximum = null;
    public float|null $wageMinimum = null;
    public int|null $fulltimeHours = null;
    public int|null $openings = null;
    public int|null $parttimeHours = null;
    public int|null $requiredYearsOfExperience = null;
    public string $functionName = '';
    public string $id = '';
    public string $officeId = '';
    public string|null $certificates = null;
    public string|null $contractType = null;
    public string|null $description = null;
    public string|null $descriptionLevel1 = null;
    public string|null $education = null;
    public string|null $endDate = null;
    public string|null $expertise = null;
    public string|null $extra = null;
    public string|null $offer = null;
    public string|null $postCode = null;
    public string|null $sector = null;
    public string|null $skills = null;
    public string|null $startDate = null;
    public string|null $tasksAndProfiles = null;
    public string|null $wageDuration = null;
    public string|null $wageInformation = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}