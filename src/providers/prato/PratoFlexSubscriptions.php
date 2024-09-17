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
use yii\base\ExitException;

class PratoFlexSubscriptions extends Component
{

    const  GO4JOBS = 2;

    /**
     * This function might not be necessary, probably not, deprecated
     * @throws GuzzleException
     * @deprecated
     */
    public function createUser(Submission $submission): void {
        $cmsOffice = collect($submission->office->id)->first();

        if($cmsOffice !== '') {
            // get the office code first!
            $atsOffice = $this->getOfficeCode($cmsOffice);
            $office = Ats::$plugin->offices->getBranchById($cmsOffice);
            $data = $this->_prepareUserData($submission, $office);

            // Let's make this user in prato
            $response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $data);
            $pratoUser = $response->id;

            Ats::$plugin->log("Creating user for office code: {$atsOffice->officeCode}");

            $this->_pushCv($submission, $atsOffice, $pratoUser);
        }
    }

    /**
     * @param Submission $submission
     * @throws ExitException
     * @throws GuzzleException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function createUserApplication(Submission $submission, bool $spontaneous = false): void {
        $cmsOffice = collect($submission->selectedOffice->id)->first();
        // get the office code first!
        $atsOffice = $this->getOfficeCode($cmsOffice);

        $office = Ats::$plugin->offices->getBranchById($cmsOffice);

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
     * @throws GuzzleException
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
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidFieldException
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

    private function _prepareUserData(Submission $submission, OfficeModel $office): array
    {
        return [
            'firstname' => (string) $submission->firstName,
            'name' => (string) $submission->lastName,
            'email' => (string) $submission->email,
            'city' => (string) $submission->city,
            'street' => (string) $submission->addressLine1,
            'zip' => (string) $submission->postCode,
            'mailboxnumber' => (string) $submission->addressLine2,
            'office' => $office->branchId,
            'inss' => (string) $submission->inss,
            'info' => (string) $submission->about,
            'telephone' => (string) $sumibission->phone,
            'language' => 2,
            'recruitmentchannel' => self::GO4JOBS,
        ];
    }

    private function _pushCv(Submission $submission, object $office, int $pratoUser): void {
        if (($submission->documents->one() ?? null) && (!empty($pratoUser))) {
            $cvUrl = $submission->documents->one()->url;
            $cvName = $submission->documents->one()->filename;
            $cvFile = new CURLFile($cvUrl, null, $cvName);

            // Let's add the CV to the user
            Ats::$plugin->pratoProvider->pushCvToUser($office, $pratoUser, $cvFile);
        }
    }
}
