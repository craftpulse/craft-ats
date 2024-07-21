<?php

namespace craftpulse\ats\providers\prato;

use Craft;
use craft\events\ModelEvent;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;

use craftpulse\ats\Ats;

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
        // get the office code first!
        $atsOffice = $this->getOfficeCode($cmsOffice);
        $office = Ats::$plugin->offices->getBranchById($cmsOffice);

        $data = [
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

        // Let's make this user in prato
        $user = Ats::$plugin->pratoProvider->pushUser($atsOffice, $data);

        Craft::info("Creating user for office code: {$atsOffice->officeCode}", __METHOD__);

        if (($submission->documents->one() ?? null) && (!empty($user))) {
            $cvUrl = $submission->documents->one()->url;
            $cvName = $submission->documents->one()->filename;
            $cvFile = new CURLFile($cvUrl, null, $cvName);

            // Let's add the CV to the user
            Ats::$plugin->pratoProvider->pushCvToUser($atsOffice, $user, $cvFile);
        }

    }

    public function createUserApplication(Submission $submission): void {
        $cmsOffice = collect($submission->office->id)->first();
        // get the office code first!
        $atsOffice = $this->getOfficeCode($cmsOffice);
        $office = Ats::$plugin->offices->getBranchById($cmsOffice);

        // check if user exists
        $user = Ats::$plugin->users->getUserByUsername($submission->email);

        $data = [
            'motivation' => (string) $submission->motivation,
        ];

        Ats::$plugin->pratoProvider->pushApplication($atsOffice, $user, $data);
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
}
