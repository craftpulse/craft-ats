<?php

    namespace craftpulse\ats\models;

    use Craft;
    use craft\base\Model;

    /**
     * Client Model model
     */
    class ClientModel extends Model
    {
        public string $communicationType = '';
        public string|null $contact = null;
        public string|null $telephone = '';
        public string|null $emailAddress = null;

        protected function defineRules(): array
        {
            return array_merge(parent::defineRules(), [
                // ...
            ]);
        }
    }
