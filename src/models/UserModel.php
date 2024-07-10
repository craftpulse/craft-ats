<?php

namespace craftpulse\ats\models;

use craft\base\Model;

/**
 * Client Model model
 */
class UserModel extends Model
{
    public string $id = '';
    public string $branchId = '';
    public string $lastname = '';
    public string $firstname = '';
    public string $userId = '';
    public ?string $email = null;

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}
