<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\ClientModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**addrepublipub
 * Office Service service
 */
class SyncOfficesService extends Component
{
    /**
     * @event BranchEvent
     */
    public const EVENT_BEFORE_SYNC_BRANCH = 'beforeSyncBranch';

    /**
     * @event BranchEvent
     */
    public const EVENT_AFTER_SYNC_BRANCH = 'afterSyncBranch';

    /**
     * @var null|object
     */
    public ?object $provider = null;

    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    public function getBranchById(int $branchId): ?OfficeModel
    {
        if (!$branchId) {
            return null;
        }

        $branchRecord = Entry::find()
            ->section(Ats::$plugin->settings->officeHandle)
            ->branchId($branchId)
            ->anyStatus()
            ->one();

        if ($branchRecord === null) {
            return null;
        }

        $branch = new OfficeModel();
        $branch->setAttributes($branchRecord->getAttributes(), false);
        $branch->branchId = $branchRecord->branchId;

        return $branch;
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ElementNotFoundException
     */
    public function saveBranch(OfficeModel $branch, ?object $office = null): bool
    {
        if ($branch->validate() === false) {
            return false;
        }

        if ($branch->branchId) {
            $branchRecord = Entry::find()
                ->id($branch->branchId)
                ->status(null)
                ->one();

            if ($branchRecord === null) {
                // CREATE NEW
                $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->officeHandle);

                if ($section) {
                    $branchRecord = new Entry([
                        'sectionId' => $section->id
                    ]);
                }
            } else {
                // UPDATE
                var_dump('We update our branchy');
            }

            $branchRecord->title = $branch->name;
            $branchRecord->branchId = $branch->branchId;
            $branchRecord->province = [$branch->provinceId ?? null];
            $branchRecord->latitude = $branch->latitude;
            $branchRecord->longitude = $branch->longitude;
            $branchRecord->city = $branch->city;
            $branchRecord->postCode = $branch->postCode;
            $branchRecord->addressLine1 = $branch->street;

            $enabledForSites = [];
            foreach($branchRecord->getSupportedSites() as $site) {
                $enabledForSites[] = $site['siteId'];
            }
            $branchRecord->setEnabledForSite($enabledForSites);
            $branchRecord->enabled = true;

            $saved = Craft::$app->getElements()->saveElement($branchRecord);

            return $saved;
        }

        return false;
    }

    public function syncBranches(callable $progressHandler = null, bool $queue = true): void
    {
        $this->provider->fetchBranches();
    }
































    public function fetchOffice(string $id): ?Entry
    {
        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $office = $provider->fetchOffice($id);

        if ($office) {
            return $this->upsertOffice($office);
        }

        return null;
    }

    public function fetchContactByCompanyNumber(string $companyNumber): ?Entry
    {
        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $client = $provider->fetchContactByCompanyNumber($companyNumber);

        if ($client) {
            return $this->upsertContact($client);
        }

        return null;
    }

    public function fetchContactByClientId(string $id): ?Entry
    {
        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $client = $provider->fetchContactByClientId($id);

        if ($client) {
            return $this->upsertContact($client);
        }

        return null;
    }

    public function upsertOffice(OfficeModel $officeModel): ?Entry
    {
        // @TODO: check if office exists -> no Mapbox calls
        //$mapboxService = new MapboxService();
        //$locationService = new LocationService();

        // get office or create new entry
        $office = $this->getOfficeById($officeModel->id);
        if (is_null($office)) {
            $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->officeHandle);

            if ($section) {
                $office = new Entry([
                    'sectionId' => $section->id
                ]);
            }
        }

        // contact
        $contact = $this->fetchContactByCompanyNumber($officeModel->companyNumber);

        // location
        $officeModel->province = $office->province->anyStatus()->one()->title ?? null;
        if (($office->addressLine1 ?? '') !== $officeModel->addressLine1 || ($office->latitude == '' || $office->longitude == '')) {
            $location = $mapboxService->getAddress($officeModel->addressLine1. ' ' .$officeModel->city . ' België');

            if ($location['geometry'] ?? null) {
                $officeModel->latitude = $location['geometry']['coordinates'][1] ?? null;
                $officeModel->longitude = $location['geometry']['coordinates'][0] ?? null;
            }

            if ($location['properties'] ?? null) {
                $officeModel->postCode = $location['properties']['context']['postcode']['name'] ?? null;
                $officeModel->province = $location['properties']['context']['region']['name'] ?? null;
            }
        }

        $office->title = $officeModel->name ?? '';
        $office->addressLine1 = $officeModel->addressLine1 ?? $office->addressLine1 ?? '';
        $office->postCode = $officeModel->postCode ?? $office->postCode ?? '';
        $office->city = $officeModel->city ?? $office->city ?? '';
        $office->latitude = $officeModel->latitude ?? $office->latitude ?? '';
        $office->longitude = $officeModel->longitude ?? $office->longitude ?? '';
        $office->taxNumber = $officeModel->taxNumber ?? '';
        $office->registrationNumber = $officeModel->registrationNumber ?? '';
        $office->companyNumber = $officeModel->companyNumber ?? '';
        $office->province = ($officeModel->province ?? null) ? [$locationService->upsertProvince($officeModel->province)] : null;
        $office->branchId = $officeModel->id;
        $office->contact = [$contact->id ?? null];

        $enabledForSites = [];
        foreach($office->getSupportedSites() as $site) {
            array_push($enabledForSites, $site['siteId']);
        }
        $office->setEnabledForSite($enabledForSites);
        $office->enabled = true;

        $saved = Craft::$app->getElements()->saveElement($office);

        // return category
        return $saved ? $office : null;
    }

    public function getContactByInss(string $inss): ?Entry
    {
        return Entry::find()
            ->section(Ats::$plugin->settings->contactsHandle)
            ->inss($inss)
            ->anyStatus()
            ->one();
    }

    public function getCommunicationByType(string $type): ?Entry
    {
        return null;
    }

    public function upsertContact(ClientModel $client): ?Entry
    {
        $contact = $this->getContactByInss($client->inss);

        $types = ['email','phone','whatsapp'];

        if (is_null($contact)) {
            $section = Craft::$app->entries->getSectionByHandle(Ats::$plugin->settings->contactsHandle);

            if ($section) {
                $contact = new Entry([
                    'sectionId' => $section->id
                ]);
            }
        }

        if (!is_null($contact)) {
            $contact->title = $client->name;
            $contact->inss = $client->inss;
            $contact->branchId = $client->branchId;
            $contact->standfirst = $client->info;

            $enabledForSites = [];
            foreach($contact->getSupportedSites() as $site) {
                array_push($enabledForSites, $site['siteId']);
            }
            $contact->setEnabledForSite($enabledForSites);
            $contact->enabled = true;
        }

        // save element
        $saved = Craft::$app->getElements()->saveElement($contact);

        if ($saved) {
            // empty the matrix
            foreach($contact->communication->all() as $existingComs) {
                Craft::$app->getElements()->deleteElement($existingComs);
            }

            // fill out the matrix
            foreach($client->communications as $coms) {
                $entryType = Craft::$app->entries->getEntryTypeByHandle(Ats::$plugin->settings->communicationTypeHandle);
                $field = Craft::$app->getFields()->getFieldByHandle('communication');

                $communicationType = in_array($coms['type'] ?? '', $types) ? $coms['type'] : 'other';
                $phone = $communicationType == 'phone' || $communicationType == 'whatsapp' ? $coms['value'] : null;
                $email = $communicationType == 'email' ? $coms['value'] : null;
                $other = $communicationType == 'other' ? $coms['value'] : null;

                $communication = Entry::find()->owner($contact)->typeId($entryType->id)->fieldId($field->id)->emailAddress($email)->telephone($phone)->contact($other)->one();

                if (is_null($communication)) {
                    $communication = new Entry;
                    $communication->typeId = $entryType->id;
                    $communication->fieldId = $field->id;
                }

                $communication->communicationType = $communicationType;
                $communication->contact = $other;
                $communication->telephone = $phone;
                $communication->emailAddress = $email;

                $communication->setPrimaryOwner($contact);
                $communication->setOwner($contact);

                $saved = Craft::$app->getElements()->saveElement($communication);
            }
        }

        return $saved ? $contact : null;
    }
}
