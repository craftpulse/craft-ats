<?php

namespace craftpulse\ats\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use craftpulse\ats\Ats;

use Illuminate\Support\Collection;
use Throwable;
use yii\web\BadRequestHttpException;
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

    /**
     * Syncs a single job.
     * @throws Throwable
     */
    public function actionUpsertVacancy(): ?Response
    {
        if (!Ats::$plugin->settings->syncEnabled) {
            return $this->getFailureResponse('ATS syncing is disabled');
        }

        $request = Craft::$app->getRequest();

        $urlParams = collect($request->getSegments())->reverse()->take(3);

        $params = [
            'url' => Craft::$app->getRequest()->getUrl(),
            'vacancyId' => (int) $urlParams->get(2),
            'officeCode' => $urlParams->last(),
            'status' => $urlParams->first(),
        ];

        if($params['status'] == 1) {
            Ats::$plugin->log('Request received to sync vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'], $params);
            Ats::$plugin->vacancies->syncVacancy($params['vacancyId'], $params['officeCode']);
            return $this->getSuccessResponse('Vacancy successfully queued for syncing.', $params['vacancyId']);
        } elseif($params['status'] == 0) {
            Ats::$plugin->log('Request received to remove vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'], $params);
            if(Ats::$plugin->vacancies->disableVacancy($params['vacancyId'])) {
                return $this->getSuccessResponse('Vacancy successfully disabled.', $params['vacancyId']);
            }
            return $this->getFailureResponse('Vacancy successfully disabled.', $params['vacancyId']);
        }

        return $this->getFailureResponse('Something went wrong.', $params['vacancyId']);
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
    private function getFailureResponse(string $message, ?int $vacancyId = null): ?Response
    {
        $this->setFailFlash(Craft::t('ats', $message));

        return $this->getResponse($message, $vacancyId, false);
    }

    /**
     * Returns a success response.
     */
    private function getSuccessResponse(string $message, int $vacancyId = null): ?Response
    {
        Ats::$plugin->log($message . ' [via sync utility by "{username}"]');

        $this->setSuccessFlash(Craft::t('ats', $message));

        return $this->getResponse($message, $vacancyId);
    }

    /**
     * Returns a response with the provided message
     * @throws BadRequestHttpException
     */
    private function getResponse(string $message, ?int $vacancyId, bool $success = true): ?Response
    {
        $request = Craft::$app->getRequest();

        // If front-end or JSON request
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE || $request->getAcceptsJson()) {
            return $this->asJson([
                'success' => $success,
                'message' => Craft::t('ats', $message),
                'vacancyid' => $vacancyId,
            ]);
        }

        if (!$success) {
            return null;
        }

        return $this->redirectToPostedUrl();
    }
}
