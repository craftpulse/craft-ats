<?php

namespace craftpulse\ats\controllers;

use Craft;
use craft\elements\Entry;
use craft\errors\ExitException;
use craft\web\Controller;
use craft\web\View;
use craftpulse\ats\Ats;

use Illuminate\Support\Collection;
use Throwable;
use yii\base\InvalidRouteException;
use yii\log\Logger;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
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
            Ats::$plugin->log(
                'Request received to sync vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'],
                $params
            );

            if(Ats::$plugin->vacancies->syncVacancy($params['vacancyId'], $params['officeCode'])) {
                Ats::$plugin->log(
                    'Vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'] . ' is queued for syncing.',
                    $params
                );
                return $this->getTriggerVacancyResponse($params['vacancyId']);
            }

            Ats::$plugin->log(
                'Vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'] . 'did not exist or could not be disabled.',
                $params,
                Logger::LEVEL_ERROR
            );

            return $this->getFailureResponse('Something went wrong while syncing vacancy id: ' . $params['vacancyId'] . '. See logs for more details.');
        } elseif($params['status'] == 0) {
            Ats::$plugin->log(
                'Request received to remove vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'],
                $params
            );

            if(Ats::$plugin->vacancies->disableVacancy($params['vacancyId'])) {
                Ats::$plugin->log(
                    'Vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'] . ' successfully disabled.',
                    $params
                );

                return $this->getTriggerVacancyResponse($params['vacancyId']);
            }

            Ats::$plugin->log(
                'Vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'] . 'did not exist or could not be disabled.',
                $params,
                Logger::LEVEL_ERROR
            );

            return $this->getFailureResponse('Vacancy id:' . $params['vacancyId'] . ' did not exist or could not be disabled. See logs for more details.');
        }

        Ats::$plugin->log(
            'Vacancy id: ' . $params['vacancyId'] . ' for office: ' . $params['officeCode'] . 'did not sync for an unknown reason.',
            $params,
            Logger::LEVEL_ERROR
        );
        return $this->getFailureResponse('Something went wrong.', $params['vacancyId']);
    }

    /**
     * Redirect to an existing vacancy
     * @TODO: create this as separate service into PratoFlex stuff
     * @throws InvalidRouteException
     */
    public function actionRedirectVacancy(): void
    {
        $request = Craft::$app->getRequest();

        $urlParams = collect($request->getSegments())->reverse()->take(3);

        $params = [
            'vacancyId' => (int) $urlParams->first(),
            'officeCode' => $urlParams->get(1),
        ];

        $vacancy = Entry::find()
            ->section(Ats::$plugin->settings->jobsHandle)
            ->vacancyId($params['vacancyId'])
            ->officeCode($params['officeCode'])
            ->one();

        $response = Craft::$app->getResponse();
        $errorHandler = Craft::$app->getErrorHandler();
        $errorHandler->exception = new NotFoundHttpException();

        if($vacancy) {
            $destination = $vacancy->getUrl();
            $response->redirect($destination, 301)->send();
        } else {
            try {
                Craft::$app->runAction('templates/render-error');
            } catch (InvalidRouteException | \yii\console\Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }

            try {
                Craft::$app->end();
            } catch (ExitException $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }

        }
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
     * @throws BadRequestHttpException
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
            ]);
        }

        if (!$success) {
            return null;
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Returns a response with only the vacancy id as body
     * @throws BadRequestHttpException
     */
    private function getTriggerVacancyResponse(int $vacancyId): ?Response
    {
        $request = Craft::$app->getRequest();

        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE || $request->getAcceptsJson()) {
            return $this->asJson($vacancyId);
        }

        return $this->redirectToPostedUrl();
    }
}
