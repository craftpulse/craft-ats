<?php

namespace craftpulse\ats;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\web\TemplateResponseBehavior;

use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;

/** @noinspection MissingPropertyAnnotationsInspection */

/**
 * Class Ats
 *
 * @author    CraftPulse
 * @package   Ats
 * @since     1.0.0
 */
class Ats extends Plugin
{
    // Traits
    // =========================================================================

    // Static Properties
    // =========================================================================
    /**
     * @var ?Ats
     */
    public static ?Ats $plugin = null;

    // Public Properties
    // =========================================================================
    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;
        // Handle any console commands
        $request = Craft::$app->getRequest();
        if($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'craftpulse\ats\console\controllers';
        }
        // Install our global event handlers
        $this->installEventHandlers();
        // Log that the plugin has loaded
        Craft::info(
            Craft::t(
                'ats',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): TemplateResponseBehavior|Response
    {
        $view = Craft::$app->getView();
        $namespace = $view->getNameSpace();
        $view->setNamespace('settings');
        $settingsHtml = $this->settingsHtml;
        $view->setNamespace($namespace);
        /** var Controller $controller */
        $controller = Craft::$app->getController();

        return $controller->renderTemplate('ats/settings/index.twig', [
            'plugin' => $this,
            'settingsHtml' => $settingsHtml,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function settingsHtml(): ?string
    {
        // Get only the user-editable settings
        /** @var Settings $settings */
        $settings = $this->getSettings();

        // Render the settings template
        try {
            return Craft::$app->getView()->renderTemplate(
              'ats/settings/_settings.twig',
              [
                  'settings' => $settings,
              ]
            );
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return '';
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Install our event handlers
     */

    protected function installEventHandlers(): void
    {
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    Craft::debug(
                        'Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS',
                        __METHOD__
                    );
                    /** @var ?Settings $settings */
                    $settings = $this->getSettings();
                    if (($settings !== null) && $settings->automaticallySyncVacancies) {
                        // After the settings are saved, force a sync of all vacancies
                        Ats::$plugin->vacancies->syncAllVacancies();
                    }
                }
            }
        );
    }
}