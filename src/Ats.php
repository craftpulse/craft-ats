<?php

namespace craftpulse\ats;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Plugins;
use craft\web\TemplateResponseBehavior;
use craft\web\UrlManager;
use craftpulse\ats\models\SettingsModel;
use craftpulse\ats\services\CleanUpService;
use craftpulse\ats\services\JobService;
use craftpulse\ats\services\LocationService;
use craftpulse\ats\services\MapboxService;
use craftpulse\ats\services\OfficeService;
use yii\base\Event;
use yii\base\Exception;
use yii\web\Response;

/**
 * Class Ats
 *
 * @author    CraftPulse
 * @package   Ats
 * @since     1.0.0
 * @property-read MapboxService $mapboxService
 * @property-read OfficeService $officeService
 * @property-read LocationService $locationService
 * @property-read CleanUpService $cleanUpService
 * @property-read SettingsModel $settings
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

    /**                                                              
    * @property-read JobService $jobService
    */
    public static function config(): array                                 
    {                                                                      
        return [                                                           
            'components' => [                                              
                'jobService' => JobService::class,
                'mapboxService' => MapboxService::class,
                'officeService' => OfficeService::class,
                'locationService' => LocationService::class,
                'cleanUpService' => CleanUpService::class,
            ],                                                             
        ];                                                                 
    }

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

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerCpUrlRules();
        }

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
     * Logs a message
     */
    public function log(string $message, array $params = [], int $type = Logger::LEVEL_INFO): void
    {
        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            $params['username'] = $user->username;
        }

        $message = Craft::t('ats', $message, $params);

        Craft::getLogger()->log($message, $type, 'ats');
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'ats/_settings',
            [ 'settings' => $this->getSettings() ]
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new SettingsModel();
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
                    // $settings = $this->getSettings();
                    // if (($settings !== null) && $settings->autoSyncJobs) {
                    //     // After the settings are saved, force a sync of all vacancies
                    //     Ats::$plugin->vacancies->syncAllVacancies();
                    // }
                }
            }
        );
    }

    /**
     * Registers CP URL rules event
     */
    private function registerCpUrlRules(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Merge so that settings controller action comes first (important!)
                $event->rules = array_merge([
                    'settings/plugins/ats' => 'ats/settings/edit',
                ],
                    $event->rules
                );
            }
            );
    }

    /**
     * Registers user permissions
     */
    private function registerUserPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Ats',
                    'permissions' => [
                        'ats:syncOffices' => [
                            'label' => Craft::t('ats', 'Synchronise the offices'),
                        ],
                        'ats:syncVacancies' => [
                            'label' => Craft::t('ats', 'Synchronise the vacancies'),
                        ],
                    ]
                ];
            }
        );
    }
}