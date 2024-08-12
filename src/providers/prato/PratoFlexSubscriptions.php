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
     * @throws GuzzleException
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

            // @TODO Add returned ATS User ID to table
        }
    }

    /**
     * @throws GuzzleException
     */
    public function createUserApplication(Submission $submission): void {
        $cmsOffice = collect($submission->selectedOffice->id)->first();
        // get the office code first!
        $atsOffice = $this->getOfficeCode($cmsOffice);
        $office = Ats::$plugin->offices->getBranchById($cmsOffice);

        // check if user exists
        $user = Ats::$plugin->users->getUserByEmail($submission->email);

        // Create the user in the CMS system if it doesn't exist
        if($user === null) {
            // if the user doesn't exist, register in our CMS
            Ats::$plugin->users->createUser($submission);
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
            $this->_createPratoUser($atsOffice, $userData);
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




        // @TODO - if the officeCode does not exist in the user profile, add it, together with the pratoFlex UserID - if PratoFlex gives a positive answer for said office

        // @TODO - if PratoFlex returns a 404 - then create the user in pratoFlex, get the ID, save it in the user account (officeCode && ats ID) and push the application


        //$response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $data);
        //$pratoUser = $response->id;

        Craft::info("Creating user for office code: {$atsOffice->officeCode}", __METHOD__);



        // push new CV
        //$this->_pushCv($submission, $atsOffice, $pratoUser);


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
    private function _createPratoUser(object $atsOffice, array $userData): void
    {
        // @TODO - fully seperate function in here to create and check / add to user profile
        // create the user in PratoFlex
        $response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $userData);
        $pratoUser = $response->id;
        // @TODO response should be added to the user - seperate function
    }

    private function _getAtsUserId(User $user, string $office): ?string
    {

        foreach ($user->atsUserMapping as $atsId) {
            if($atsId->officeCode === $office) {
                return $atsId->atsUserId;
            }
        }

        return null;
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
