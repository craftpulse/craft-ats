<?php

namespace craftpulse\ats\models;

use craft\base\Model;

/**
 * @property string $id
 * @property string $branchId
 * @property string $lastname
 * @property string $firstname
 * @property string $userId
 * @property string|null $email
 */
class UserModel extends Model
{
    public string $id = '';
    public string $branchId = '';
    public string $lastname = '';
    public string $firstname = '';
    public string $userId = '';
    public ?string $email = null;
}
