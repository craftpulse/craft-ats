<?php

namespace craftpulse\ats\console\controllers;

use Craft;
use craft\elements\Entry;
use craft\elements\Category;
use craft\helpers\Console;
use craftpulse\ats\Ats;
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
        $optoins = parent::options($actionID);
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

    public function actionDeleteCategories(): int
    {
        $this->deleteCategories();

        return ExitCode::OK;
    }

    private function deleteVacancies(): void
    {
        if($this->queue) {
            $settings = Ats::$plugin->settings;

            // Get all of the vacancy entries
            $entries = Entry::find()
                ->section($settings->jobsHandle)
                ->anyStatus()
                ->all();

            // Delete all the vacancies
            foreach ($entries as $entry) {
                if (Craft::$app->elements->deleteElementById($entry->id)) {
                    $this->stdout("Deleted vacancy: {$entry->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete vacancy: {$entry->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All vacancies deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }

    private function deleteOffices(): void
    {
        if($this->queue) {
            $settings = Ats::$plugin->settings;

            // Get all of the office entries
            $entries = Entry::find()
                ->section($settings->officeHandle)
                ->anyStatus()
                ->all();

            // Delete all the offices
            foreach ($entries as $entry) {
                if (Craft::$app->elements->deleteElementById($entry->id)) {
                    $this->stdout("Delete office: {$entry->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete office: {$entry->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All offices deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }

    private function deleteCategories(): void
    {
        if($this->queue) {
            $settings = Ats::$plugin->settings;

            // Get all of the category entries
            $categories = Category::find()
                ->status(null)
                ->all();

            // Delete all the offices
            foreach ($categories as $category) {
                if (Craft::$app->elements->deleteElementById($category->id)) {
                    $this->stdout("Delete category: {$category->title}" . PHP_EOL, Console::FG_GREEN);
                } else {
                    $this->stdout("Failed to delete category: {$category->title}" . PHP_EOL, Console::FG_RED);
                }
            }

            $this->stdout("All categories deleted." . PHP_EOL, Console::FG_GREEN);
        }
    }
}
