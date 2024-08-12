<?php

namespace craftpulse\ats\providers\prato;

use Craft;
use craft\events\ModelEvent;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;

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
        $user = Ats::$plugin->users->getUserByUsername($submission->email);

        // @TODO - check if user exists in Prato - if not, then push it.

        if($user === null) {
            // if the user doesn't exist, register in our CMS
            Ats::$plugin->users->createUser($submission);
            // @TODO add the user to the applicants group
            // ...
        } else {
            $pratoUser = $user->atsId;
        }

        // @TODO check if user exists in prato - if not create it, and add info to mapping table in user account.
        $data = $this->_prepareUserData($submission, $office);
        $response = Ats::$plugin->pratoProvider->pushUser($atsOffice, $data);
        $pratoUser = $response->id;
        Craft::info("Creating user for office code: {$atsOffice->officeCode}", __METHOD__);

        $applicationData = [
            'vacancy' => $submission->job->collect()->first()->vacancyId,
            'office' => $office->branchId,
            'motivation' => (string) $submission->motivation,
        ];

        // push new CV
        $this->_pushCv($submission, $atsOffice, $pratoUser);

        Ats::$plugin->pratoProvider->pushApplication($atsOffice, $pratoUser, $applicationData);
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
