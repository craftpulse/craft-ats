<?php

namespace craftpulse\ats\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use craftpulse\ats\Ats;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class SyncController extends Controller
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if(!parent::beforeAction($action)) {
            return false;
        }

        $request = Craft::$app->getRequest();

        // Require permission if posted from utility
        if ($request->getIsPost() && $request->getParam('utility')) {
            $this->requirePermission('ats:' . $action->id);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result): mixed
    {
        // If front-end request, run the queue to ensure action is completed in full
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE) {
            Craft::$app->runAction('queue/run');
        }

        return parent::afterAction($action, $result);
    }

    /**
     * Syncs the offices.
     */
    public function actionSyncOffices(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        Ats::$plugin->offices->syncBranches();

        return $this->getSuccessResponse('Offices succesfully queued for syncing.');
    }

    public function actionSyncSectors(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        Ats::$plugin->sectors->syncSectors();

        return $this->getSuccessResponse('Sectors successfully queued for syncing.');
    }

    public function actionSyncCodes(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        Ats::$plugin->codes->syncCodes();

        return $this->getSuccessResponse('Codes successfully queued for syncing.');
    }

    public function actionSyncFunctions(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        Ats::$plugin->functions->syncFunctions();

        return $this->getSuccessResponse('Functions successfully queued for syncing.');
    }

    /**
     * Syncs the jobs.
     */
    public function actionSyncVacancies(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        Ats::$plugin->vacancies->syncVacancies();

        return $this->getSuccessResponse('Vacancies successfully queued for syncing.');
    }

    /**
     * Returns a failure response
     */
    private function getFailureResponse(string $message): ?Response
    {
        $this->setFailFlash(Craft::t('ats', $message));

        return $this->getResponse($message, false);
    }

    /**
     * Returns a success response.
     */
    private function getSuccessResponse(string $message): ?Response
    {
        Ats::$plugin->log($message . ' [via sync utility by "{username}"]');

        $this->setSuccessFlash(Craft::t('ats', $message));

        return $this->getResponse($message);
    }

    /**
     * Returns a response with the provided message
     */
    private function getResponse(string $message, bool $success = true): ?Response
    {
        $request = Craft::$app->getRequest();

        // If front-end or JSON request
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE || $request->getAcceptsJson()) {
            return $this->asJson([
                'success' => $success,
                'message' => Craft::t('ats', $message),
            ]);
        }

        if (!$success) {
            return null;
        }

        return $this->redirectToPostedUrl();
    }
}