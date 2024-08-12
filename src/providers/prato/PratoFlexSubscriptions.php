<?php

namespace craftpulse\ats\providers\prato;

use Craft;
use craft\elements\User;
use craft\helpers\App;

use craftpulse\ats\Ats;

use craftpulse\ats\models\OfficeModel;
use CURLFile;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;

use yii\base\Component;
use verbb\formie\elements\Submission;

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

            Craft::info("Creating user for office code: {$atsOffice->officeCode}", __METHOD__);

            $this->_pushCv($submission, $atsOffice, $pratoUser);
        }
    }

    /**
     * @throws GuzzleException
     */
    public function createUserApplication(Submission $submission): string {
        $cmsOffice = collect($submission->selectedOffice->id)->first();
        // get the office code first!
        $atsOffice = $this->getOfficeCode($cmsOffice);

        $office = Ats::$plugin->offices->getBranchById($cmsOffice);

        // check if user exists
        $user = Ats::$plugin->users->getUserByEmail($submission->email);

        // Create the user in the CMS system if it doesn't exist
        if($user === null) {
            // if the user doesn't exist, register in our CMS
            // @TODO create the user in the CMS and add it to the user variable
            $user = Ats::$plugin->users->createUser($submission);
            // ...
        }

        // Prepare the application data, we need it in any case
        $applicationData = [
            'vacancy' => $submission->job->collect()->first()->vacancyId,
            'office' => $office->branchId,
            'motivation' => (string) $submission->motivation,
        ];

        // Prepare the user data in case the response is a 404
        $userData = $this->_prepareUserData($submission, $office);

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

        Craft::info("Creating user for office code: {$atsOffice->officeCode}", __METHOD__);

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

        foreach ($user->atsUserMapping as $atsId) {
            if($atsId->officeCode === $office->officeCode) {
                return $atsId->atsUserId !== '' ? $atsId->atsUserId : null;
            }
        }

        return null;
    }

    private function _checkAtsUserId(User $user, string $atsUserId): bool
    {
        foreach ($user->atsUserMapping as $atsId) {
            if($atsId->atsUserId === $atsUserId) {
                return true;
            }
        }

        return false;
    }

    private function _addAtsIdToProfile(object $office, string $atsId, User $user): void
    {
        $mappings = $user->getFieldValue('atsUserMapping');
        $mappings[] = [
            'officeCode' => $office->officeCode,
            'atsUserId' => $atsId,
        ];
        $user->setFieldValue('atsUserMapping', $mappings);

        Craft::$app->elements->saveUser($user);
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
