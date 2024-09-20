<?php

namespace craftpulse\ats\console\controllers;

use Craft;
use craft\elements\Entry;
use craft\elements\Category;
use craft\helpers\Console;
use craftpulse\ats\Ats;
use Throwable;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Vacancies controller
 */
class VacanciesController extends Controller
{

    /**
     * @var bool Whether jobs should be only queued and not run.
     */
    public bool $queue = true;

    /**
     * @var bool Whether verbose output should be enabled
     */
    public bool $verbose = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'queue';
        $options[] = 'verbose';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getHelp(): string
    {
        return 'Ats Vacancy Actions.';
    }

    /**
     * @inheritdoc
     */
    public function getHelpSummary()
    {
        return $this->getHelp();
    }

    /**
     * Deletes all vacancies
     */
    public function actionDeleteVacancies(): int
    {
        $this->deleteVacancies();

        return ExitCode::OK;
    }

    /**
     * Deletes all offices
     */
    public function actionDeleteOffices(): int
    {
        $this->deleteOffices();

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionDeleteCategories(): int
    {
        $this->deleteCategories();

        return ExitCode::OK;
    }

    /**
     * @return void
     * @throws Throwable
     */
    private function deleteVacancies(): void
    {
        if($this->queue) {
            $settings = Ats::$plugin->settings;

            // Get all of the vacancy entries
            $entries = Entry::find()
                ->section($settings->jobsHandle)
                ->status(null)
                ->all();

            // Delete all the vacancies
            foreach ($entries as $entry) {
                if (Craft::$app->elements->deleteElementById($entry->id, null, null, true)) {
                    $this->stdout("Deleted vacancy: {$entry->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete vacancy: {$entry->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All vacancies deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }

    /**
     * @return void
     * @throws Throwable
     */
    private function deleteOffices(): void
    {
        if($this->queue) {
            $settings = Ats::$plugin->settings;

            // Get all of the office entries
            $entries = Entry::find()
                ->section($settings->officeHandle)
                ->status(null)
                ->all();

            // Delete all the offices
            foreach ($entries as $entry) {
                if (Craft::$app->elements->deleteElementById($entry->id, null, null, true)) {
                    $this->stdout("Delete office: {$entry->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete office: {$entry->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All offices deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }

    /**
     * @return void
     * @throws Throwable
     */
    private function deleteCategories(): void
    {
        if($this->queue) {
            // Get all of the category entries
            $categories = Category::find()
                ->status(null)
                ->all();

            // Delete all the offices
            foreach ($categories as $category) {
                if (Craft::$app->elements->deleteElementById($category->id, null, null, true)) {
                    $this->stdout("Delete category: {$category->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete category: {$category->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All categories deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }
}
