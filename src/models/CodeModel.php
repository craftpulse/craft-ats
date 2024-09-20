<?php

namespace craftpulse\ats\models;

use craft\base\Model;

/**
 * @property string $id
 * @property string|null $codeId
 * @property string $title
 */
class CodeModel extends Model
{
    public string $id = '';
    public ?string $codeId = null;
    public string $title = '';

    protected function defineRules(): array
    {
        return parent::defineRules();
    }
}
