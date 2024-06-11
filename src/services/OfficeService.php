<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Entry;
use craftpulse\ats\Ats;
use craftpulse\ats\models\ClientModel;
use craftpulse\ats\models\OfficeModel;
use craftpulse\ats\providers\PratoFlexProvider;
use yii\base\Component;

/**
 * Office Service service
 */
class OfficeService extends Component
{
    public function fetchOffice(string $id): ?Entry
    {
        switch (Ats::$plugin->settings->atsProvider) {
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
        switch (Ats::$plugin->settings->atsProvider) {
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
        switch (Ats::$plugin->settings->atsProvider) {
            case "pratoFlex":
                $provider = new PratoFlexProvider();
        }

        $client = $provider->fetchContactByClientId($id);

        if ($client) {
            return $this->upsertContact($client);
        }

        return null;
    }

    public function getOfficeById(int $branchId): ?Entry
    {
        return Entry::find()
            ->section(Ats::$plugin->settings->officeHandle)
            ->branchId($branchId)
            ->anyStatus()
            ->one();
    }

    public function upsertOffice(OfficeModel $officeModel): ?Entry
    {
        // @TODO: check if office exists -> no Mapbox calls
        $mapboxService = new MapboxService();
        $locationService = new LocationService();

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
