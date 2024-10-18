<?php

namespace craftpulse\ats\providers\prato;

use Craft;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidFieldException;
use craft\helpers\App;

use craftpulse\ats\Ats;

use craftpulse\ats\models\OfficeModel;
use CURLFile;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;

use Throwable;
use yii\base\Component;
use verbb\formie\elements\Submission;
use yii\base\Exception;

class PratoFlexSubscriptions extends Component
{

    /**
     * @const int;
     */
    const GO4JOBS = 2;

    /**
     * @const array
     */
    const COUNTRYCODES = [
        '1',    // USA, Canada, etc.
        '20',   // Egypt
        '30',   // Greece
        '31',   // Netherlands
        '32',   // Belgium
        '33',   // France
        '34',   // Spain
        '39',   // Italy
        '44',   // UK
        '49',   // Germany
        '52',   // Mexico
        '55',   // Brazil
        '61',   // Australia
        '62',   // Indonesia
        '63',   // Philippines
        '64',   // New Zealand
        '65',   // Singapore
        '66',   // Thailand
        '81',   // Japan
        '82',   // South Korea
        '86',   // China
        '91',   // India
        '92',   // Pakistan
        '93',   // Afghanistan
        '94',   // Sri Lanka
        '98',   // Iran
        '211',  // South Sudan
        '212',  // Morocco
        '213',  // Algeria
        '216',  // Tunisia
        '218',  // Libya
        '220',  // Gambia
        '221',  // Senegal
        '222',  // Mauritania
        '223',  // Mali
        '224',  // Guinea
        '225',  // Ivory Coast
        '226',  // Burkina Faso
        '227',  // Niger
        '228',  // Togo
        '229',  // Benin
        '230',  // Mauritius
        '231',  // Liberia
        '232',  // Sierra Leone
        '233',  // Ghana
        '234',  // Nigeria
        '235',  // Chad
        '236',  // Central African Republic
        '237',  // Cameroon
        '238',  // Cape Verde
        '239',  // São Tomé and Príncipe
        '240',  // Equatorial Guinea
        '241',  // Gabon
        '242',  // Congo
        '243',  // Democratic Republic of the Congo
        '244',  // Angola
        '245',  // Guinea-Bissau
        '246',  // British Indian Ocean Territory
        '247',  // Ascension
        '248',  // Seychelles
        '249',  // Sudan
        '250',  // Rwanda
        '251',  // Ethiopia
        '252',  // Somalia
        '253',  // Djibouti
        '254',  // Kenya
        '255',  // Tanzania
        '256',  // Uganda
        '257',  // Burundi
        '258',  // Mozambique
        '260',  // Zambia
        '261',  // Madagascar
        '262',  // Reunion (France)
        '263',  // Zimbabwe
        '264',  // Namibia
        '265',  // Malawi
        '266',  // Lesotho
        '267',  // Botswana
        '268',  // Swaziland
        '269',  // Comoros
        '290',  // Saint Helena
        '291',  // Eritrea
        '297',  // Aruba
        '298',  // Faroe Islands
        '299',  // Greenland
        '350',  // Gibraltar
        '351',  // Portugal
        '352',  // Luxembourg
        '353',  // Ireland
        '354',  // Iceland
        '355',  // Albania
        '356',  // Malta
        '357',  // Cyprus
        '358',  // Finland
        '359',  // Bulgaria
        '370',  // Lithuania
        '371',  // Latvia
        '372',  // Estonia
        '373',  // Moldova
        '374',  // Armenia
        '375',  // Belarus
        '376',  // Andorra
        '377',  // Monaco
        '378',  // San Marino
        '380',  // Ukraine
        '381',  // Serbia
        '382',  // Montenegro
        '383',  // Kosovo
        '385',  // Croatia
        '386',  // Slovenia
        '387',  // Bosnia and Herzegovina
        '389',  // North Macedonia
        '420',  // Czech Republic
        '421',  // Slovakia
        '423',  // Liechtenstein
        '500',  // Falkland Islands
        '501',  // Belize
        '502',  // Guatemala
        '503',  // El Salvador
        '504',  // Honduras
        '505',  // Nicaragua
        '506',  // Costa Rica
        '507',  // Panama
        '508',  // Saint Pierre and Miquelon
        '509',  // Haiti
        '590',  // Guadeloupe
        '591',  // Bolivia
        '592',  // Guyana
        '593',  // Ecuador
        '594',  // French Guiana
        '595',  // Paraguay
        '596',  // Martinique
        '597',  // Suriname
        '598',  // Uruguay
        '670',  // East Timor
        '672',  // Antarctica and some Australian territories
        '673',  // Brunei
        '674',  // Nauru
        '675',  // Papua New Guinea
        '676',  // Tonga
        '677',  // Solomon Islands
        '678',  // Vanuatu
        '679',  // Fiji
        '680',  // Palau
        '681',  // Wallis and Futuna
        '682',  // Cook Islands
        '683',  // Niue
        '685',  // Samoa
        '686',  // Kiribati
        '687',  // New Caledonia
        '688',  // Tuvalu
        '689',  // French Polynesia
        '690',  // Tokelau
        '691',  // Micronesia
        '692',  // Marshall Islands
        '850',  // North Korea
        '852',  // Hong Kong
        '853',  // Macau
        '855',  // Cambodia
        '856',  // Laos
        '880',  // Bangladesh
        '886',  // Taiwan
        '960',  // Maldives
        '961',  // Lebanon
        '962',  // Jordan
        '963',  // Syria
        '964',  // Iraq
        '965',  // Kuwait
        '966',  // Saudi Arabia
        '967',  // Yemen
        '968',  // Oman
        '971',  // United Arab Emirates
        '972',  // Israel
        '973',  // Bahrain
        '974',  // Qatar
        '975',  // Bhutan
        '976',  // Mongolia
        '977',  // Nepal
        '992',  // Tajikistan
        '993',  // Turkmenistan
        '994',  // Azerbaijan
        '995',  // Georgia
        '996',  // Kyrgyzstan
        '998',  // Uzbekistan
    ];

    /**
     * @param Submission $submission
     * @param bool $spontaneous
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws GuzzleException
     * @throws Throwable
     */
    public function createUserApplication(Submission $submission, bool $spontaneous = false): void {
        // get the office code first!
        $cmsOfficeEntry = $submission->selectedOffice->status(null)->one();
        $cmsOffice = collect($submission->selectedOffice->status(null)->id)->first();
        $atsOffice = $this->getOfficeCode($cmsOffice);
        $office = Ats::$plugin->offices->getBranchByBranchId($cmsOfficeEntry->branchId);

        // check if user exists
        $user = Ats::$plugin->users->getUserByEmail($submission->email);

        // Create the user in the CMS system if it doesn't exist
        if($user === null) {
            // if the user doesn't exist, register in our CMS
            $user = Ats::$plugin->users->createUser($submission);
            Ats::$plugin->log('User<' . $submission->email . '> did not exist in the system, and has been created.');
        } else {
            Ats::$plugin->log('User<' . $submission->email . '> is already registered and will be used.');
        }

        // Prepare the application data, we need it in any case
        $applicationData = [
            'office' => $office->branchId,
            'motivation' => (string) $submission->motivation,
        ];

        // Prepare the user data in case the response is a 404
        $userData = $this->_prepareUserData($submission, $office);

        if(!$spontaneous) {
            $vacancy = $submission->job->collect()->first();
            $applicationData['vacancy'] = $vacancy->vacancyId;
            Ats::$plugin->log('User<' . $submission->email . '> applied for: ' . $vacancy->vacancyId . '-' . $vacancy->title);
        } else {
            Ats::$plugin->log('User<' . $submission->email . '> did a spontaneous application');
        }

        // Check the table field in the user profile
        $pratoUser = $this->_getAtsUserId($user, $atsOffice);

        if($pratoUser === null) {
            $pratoUser = $this->_createPratoUser($atsOffice, $userData, $user);
        }

        // check if the user has applied for the job
        $applied = Ats::$plugin->pratoProvider->pushApplication($atsOffice, $pratoUser, $applicationData);

        // if the user could not apply, it means the user did not exist and we got a 404 on the endpoint
        if(!$applied) {
            // create the user in PratoFlex
            $response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $userData);
            $pratoUser = $response->id;

            // push the application
            Ats::$plugin->pratoProvider->pushApplication($atsOffice, $pratoUser, $applicationData);
        }

        Ats::$plugin->log("Creating user for office code: {$atsOffice->officeCode}");

        // push new CV
        $this->_pushCv($submission, $atsOffice, $pratoUser);
    }

    /**
     * @param string $office
     * @return object
     */
    private function getOfficeCode(string $office): object
    {
        $settings = Ats::$plugin->settings;

        $officeCode = Ats::$plugin->offices->getOfficeCodeByBranch($office);
        $offices = collect([]);
        foreach ($settings->officeCodes as $office) {
            $offices->push([
                'officeToken' => $office['officeToken'],
                'officeCode' => App::parseEnv($office['officeCode']),
            ]);
        }

        $atsOffice = $offices->where('officeCode', $officeCode)->first();

        return (object) $atsOffice;
    }

    /**
     * @param object $atsOffice
     * @param array $userData
     * @param User $user
     * @return int
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws GuzzleException
     * @throws InvalidFieldException
     * @throws Throwable
     */
    private function _createPratoUser(object $atsOffice, array $userData, User $user): int
    {
        // create the user in PratoFlex
        $response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $userData);
        $pratoUserId = $response->id;

        // check if the returned ID of pratoFlex is already added to the user in the CMS
        $exists = $this->_checkAtsUserId($user, $pratoUserId);

        if(!$exists) {
            // add the returned ID to the user profile
            $this->_addAtsIdToProfile($atsOffice, $pratoUserId, $user);
        }

        return $pratoUserId;
    }

    /**
     * @param User $user
     * @param object $office
     * @return string|null
     */
    private function _getAtsUserId(User $user, object $office): ?string
    {
        if (!is_null($user->atsUserMapping)) {
            foreach ($user->atsUserMapping as $atsId) {
                if ($atsId['officeCode'] === $office->officeCode) {
                    return $atsId['atsUserId'] !== '' ? $atsId['atsUserId'] : null;
                }
            }
        }
        return null;
    }

    /**
     * @param User $user
     * @param string $atsUserId
     * @return bool
     */
    private function _checkAtsUserId(User $user, string $atsUserId): bool
    {
        if (!is_null($user->atsUserMapping)) {
            foreach ($user->atsUserMapping as $atsId) {
                if ($atsId['atsUserId'] === $atsUserId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param object $office
     * @param string $atsId
     * @param User $user
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidFieldException
     * @throws Throwable
     */
    private function _addAtsIdToProfile(object $office, string $atsId, User $user): void
    {
        $mappings = $user->getFieldValue('atsUserMapping');
        $mappings[] = [
            'officeCode' => $office->officeCode,
            'atsUserId' => $atsId,
        ];
        $user->setFieldValue('atsUserMapping', $mappings);

        Craft::$app->getElements()->saveElement($user);
    }

    /**
     * @param Submission $submission
     * @param OfficeModel $office
     * @return array
     */
    private function _prepareUserData(Submission $submission, OfficeModel $office): array
    {
        return [
            'firstname' => (string) $submission->firstName,
            'name' => (string) $submission->lastName,
            'email' => (string) $submission->email,
            'city' => (string) $submission->city,
            'street' => (string) $submission->addressLine1,
            'zip' => (string) $submission->postCode,
            'housenumber' => (string) $submission->number,
            'mailboxnumber' => (string) $submission->addressLine2,
            'office' => $office->branchId,
            'inss' => (string) $submission->inss,
            'info' => (string) $submission->about,
            'phone' => $this->_formatPhoneNumber($submission->phone),
            'mobile' => $this->_formatPhoneNumber($submission->mobile),
            'language' => 1,
            'recruitmentchannel' => self::GO4JOBS,
        ];
    }

    /**
     * @param Submission $submission
     * @param object $office
     * @param int $pratoUser
     * @return void
     */
    private function _pushCv(Submission $submission, object $office, int $pratoUser): void {
        if (($submission->documents->one() ?? null) && (!empty($pratoUser))) {
            $cvUrl = $submission->documents->one()->url;
            $cvName = $submission->documents->one()->filename;
            $cvFile = new CURLFile($cvUrl, null, $cvName);

            // Let's add the CV to the user
            Ats::$plugin->pratoProvider->pushCvToUser($office, $pratoUser, $cvFile);
        }
    }

    private function _formatPhoneNumber(string $phone): string
    {
        // Remove all spaces, dashes, slashes, underscores, and non-digit characters except '+'.
        $normalizedNumber = preg_replace('/[^\d+]/', '', $phone);

        // If the number starts with a '+', replace it with '00'
        if (str_starts_with($normalizedNumber, '+')) {
            $normalizedNumber = '00' . substr($normalizedNumber, 1); // Replace '+' with '00'
        }

        // Check the country code against our list of known country codes (1 to 3 digits)
        foreach (self::COUNTRYCODES as $code) {
            if (str_starts_with($normalizedNumber, $code)) {
                $countryCode = $code;
                $localNumber = substr($normalizedNumber, strlen($countryCode));

                // Format as (countryCode)localNumber
                return "({$countryCode}){$localNumber}";
            }
        }

        // Return the original number if it doesn't match the expected pattern
        return $phone;
    }
}
