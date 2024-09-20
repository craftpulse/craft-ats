<?php

namespace craftpulse\ats\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use craftpulse\ats\Ats;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
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
     * @throws MissingComponentException
     * @throws BadRequestHttpException|MethodNotAllowedHttpException
     * @throws ForbiddenHttpException|Throwable
     */
    public function actionSave(): ?Response
    {

        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('seomatic:plugin-settings')) {
            throw new ForbiddenHttpException('You do not have permission to edit SEOmatic plugin settings.');
        }
        $general = Craft::$app->getConfig()->getGeneral();
        if (!$general->allowAdminChanges) {
            throw new ForbiddenHttpException('Unable to edit SEOmatic plugin settings because admin changes are disabled in this environment.');
        }

        $this->requirePostRequest();
        $postedSettings = Craft::$app->getRequest()->getBodyParam('settings', []);

        $settings = Ats::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Validate
        $settings->validate();

        if ($settings->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('ats', 'Couldn’t save plugin settings.'));
            return null;
        }

        // Save it
        if (!Craft::$app->getPlugins()->savePluginSettings(Ats::$plugin, $settings->getAttributes())) {
            Craft::$app->getSession()->setError(Craft::t('ats', 'Couldn’t save plugin settings.'));
        };

        $notice = Craft::t('ats', 'Plugin settings saved.');
        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }

}
