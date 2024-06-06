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

class SettingsModel extends Model
{
    /**
     * @var bool Should the vacancies automatically be synced when saving the settings of the plugin?
     */
    public bool $autoSyncJobs = true;

    /**
     * @var string The handle type of the category where contract types are stored
     */
    public string $contractTypeHandle = 'contractTypes';

    /**
     * @var string The handle type of the category where sectors are stored
     */
    public string $sectorHandle = 'sectors';

    /**
     * @var string The handle type of the category where shifts are stored
     */
    public string $shiftHandle = 'shifts';

    /**
     * @var string The handle type of the category where work regimes are stored
     */
    public string $workRegimeHandle = 'workRegimes';

    /**
     * @var string The handle type of the category where driving licenses are stored
     */
    public string $drivingLicenseHandle = 'drivingLicenses';

    /**
     * @var string The handle type of the category where places are stored
     */
    public string $placesHandle = 'places';

    /**
     * @var string The handle type of the category where provinces are stored
     */
    public string $provincesHandle = 'provinces';

    /**
     * @var string The handle type of the section where jobs are stored
     */
    public string $jobsHandle = 'jobs';

    /**
     * @var string The handle type of the section where offices are stored
     */
    public string $officeHandle = 'offices';

    /**
     * @var string The handle type of the section where office contacts are stored
     */
    public string $contactsHandle = 'contacts';

    /**
     * @var string The handle type of the entry type where communications inside of the matrix lives
     */
    public string $communicationTypeHandle = 'communication';

    /**
     * @var string the API scoped key of mapbox
     */
    public string $mapboxApiKey = '';

    /**
     * @var string the API scoped key of mapbox
     */
    public string $atsEndpoint = '';

    /**
     * @var string the API scoped key of mapbox
     */
    public string $atsAccesToken = '';

    /**
     * @var string the provider of the ATS
     */
    public string $atsProvider = 'pratoFlex';
}