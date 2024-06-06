<?php

namespace craftpulse\ats\models;

use Craft;
use craft\base\Model;

/**
 * Client Model model
 */
class ClientModel extends Model
{
    public string $id = '';
    public string $branchId = '';
    public string $name = '';
    public string $inss = '';
    public string|null $info = null;
    public array|null $communications = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
