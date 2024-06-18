<?php

namespace craftpulse\ats\controllers;

use Craft;
use craft\web\Controller;
use craftpulse\ats\Ats;
use yii\web\Response;

class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $this->requireAdmin();

        return parent::beforeAction($action);
    }

    /**
     * Edit the plugin settings.
     */
    public function actionEdit(): ?Response
    {
        $settings = Ats::$plugin->settings;

        return $this->renderTemplate('ats/_settings', [
           'settings' => $settings,
           //'config' => Craft::$app->getConfig()->getConfigFromFile('ats'),
        ]);
    }

    /**
     * Saves the plugin settings
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);

        $settings = Ats::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Validate
        $settings->validate();

        if ($settings->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('ats', 'Couldn’t save plugin settings.'));
            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Ats::$plugin, $settings->getAttributes());
        $notice = Craft::t('ats', 'Plugin settings saved.');
        $errors = [];

        if(!empty($errors)) {
            Craft::$app->getSession()->setError($notice . ' ' . implode(' ', $errors));
            return null;
        }

        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }

}