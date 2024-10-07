<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craftpulse\ats\Ats;
use craftpulse\ats\models\UserModel;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use Illuminate\Support\Collection;
use Throwable;
use verbb\formie\elements\Submission;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class SyncUsersService extends Component
{
    /**
     * @event CodeEvent
     */
    public const EVENT_BEFORE_SYNC_USER = 'beforeSyncUser';

    /**
     * @event CodeEvent
     */
    public const EVENT_AFTER_SYNC_USER = 'afterSyncUser';

    /**
     * @var null|object
     */
    public ?object $provider = null;

    /**
     * @var bool
     */
    public bool $sendActivationEmail = true;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        switch (Ats::$plugin->settings->atsProviderType) {
            case "pratoFlex":
                $this->provider = new PratoFlexProvider();
        }
    }

    /**
     * Fetching a job advisor - not a Craft CMS User
     * @param Collection $userData
     * @param string $handle
     * @return UserModel|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function getUserById(Collection $userData, string $handle): ?UserModel
    {
        if($userData->isEmpty()) {
            return null;
        }

        $userRecord = Entry::find()
            ->section($handle)
            ->userId($userData->get('id'))
            ->status(null)
            ->one();

        $user = new UserModel();

        if ($userRecord === null) {
            $user->userId = $userData->get('id');
            $user->firstname = $userData->get('firstname');
            $user->lastname = $userData->get('name');
            $user->email = strtolower($userData->get('email'));
            $user->branchId = $userData->get('branchid');

            $this->saveUser($user, $handle, true);
        } else {
            $user->setAttributes($userRecord->getAttributes(), false);
        }

        return $user;
    }

    /**
     * The function to save a job advisor - not a Craft CMS user
     * @param UserModel $user
     * @param string $handle
     * @param bool|null $isNew
     * @return bool
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function saveUser(UserModel $user, string $handle, ?bool $isNew): bool
    {
        if ($user->validate() === false) {
            return false;
        }

        $userRecord = null;

        if ($user->userId && $user->branchId && !$isNew) {
            // Look for it and update it.
            $userRecord = Entry::find()
                ->section($handle)
                ->userId($user->userId)
                ->status(null)
                ->one();

            // Create a new one, just in case it isn't found, but it was not labelled as new.
            if ($userRecord === null) {
                // CREATE NEW
                $section = Craft::$app->entries->getSectionByHandle($handle);

                if ($section) {
                    $userRecord = new Entry([
                        'sectionId' => $section->id
                    ]);
                }
            }
        } else {
            $section = Craft::$app->entries->getSectionByHandle($handle);

            if ($section) {
                $userRecord = new Entry([
                    'sectionId' => $section->id
                ]);
            }
        }

        $userRecord->title = "{$user->firstname} {$user->lastname}";
        $userRecord->branchId = $user->branchId;
        $userRecord->userId = $user->userId;
        $userRecord->email = $user->email;
        $enabledForSites = [];
        foreach ($userRecord->getSupportedSites() as $site) {
            $enabledForSites[] = $site['siteId'];
        }
        $userRecord->setEnabledForSite($enabledForSites);
        $userRecord->enabled = true;

        if(!empty($userRecord)) {
            return Craft::$app->getElements()->saveElement($userRecord);
        }

        return false;
    }

    /**
     * Updating the user in Craft CMS with an atsId if the user already exists.
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function updateUser(object $user): bool
    {
        $userRecord = User::find()
            ->username($user->email)
            ->status(null)
            ->cache()
            ->one();

        if ($userRecord) {
            $userRecord->atsId = (string) $user->id;
            return Craft::$app->getElements()->saveElement($userRecord);
        }

        return false;
    }

    /**
     * @param Submission $submission
     * @return User|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     * @throws InvalidElementException
     */
    public function createUser(Submission $submission): ?User
    {
        $user = new User();
        $user->username = $submission->email;
        $user->email = $submission->email;
        $user->firstName = $submission->firstName;
        $user->lastName = $submission->lastName;
        $user->phone = $submission->phone;
        $user->postCode = $submission->postCode;
        $user->addressLine1 = $submission->addressLine1;
        $user->addressLine2 = $submission->addressLine2;
        $user->city = $submission->city;
        $user->province = $submission->province;
        $user->about = $submission->motivation;

        if (!$user->id) {
            $user->pending = true;
        }

        // @TODO - save CV to the user field after applying
        // @TODO - fill in the CV if the user has one in their user account

        $success = Craft::$app->getElements()->saveElement($user);

        if ($success) {
            // @TODO - this is only temporary
            //Craft::$app->users->activateUser($user);

            // Assign the user to the group
            $userGroup = Craft::$app->userGroups->getGroupByHandle('applicants');
            if (!Craft::$app->getUsers()->assignUserToGroups($user->id, [$userGroup->id])) {
                throw new Exception('Could not assign user to the group');
            }

            if ($user->getStatus() == User::STATUS_PENDING) {
                if ($this->sendActivationEmail) {
                    Craft::$app->getUsers()->sendActivationEmail($user);
                }
            }

            return $user;
        } else {
            return null;
        }
    }

    /**
     * @param string $atsId
     * @return User|null
     */
    public function getUserByAtsId(string $atsId): ?User
    {
        return User::find()
            ->atsId($atsId)
            ->status(null)
            ->cache()
            ->one();
    }

    /**
     * @param string $username
     * @return User|null
     * @throws Throwable
     */
    public function getUserByUsername(string $username): ?User
    {
        return User::find()
            ->username($username)
            ->status(null)
            ->cache()
            ->one();
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::find()
            ->email($email)
            ->status(null)
            ->cache()
            ->one();
    }

}
