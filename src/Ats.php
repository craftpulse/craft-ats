<?php

namespace craftpulse\ats;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\User;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\ModelEvent;
use craft\helpers\Json;
use craft\log\MonologTarget;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\UrlManager;
use craftpulse\ats\models\SettingsModel;
use craftpulse\ats\providers\prato\PratoFlexMapper;
use craftpulse\ats\providers\prato\PratoFlexProvider;
use craftpulse\ats\providers\prato\PratoFlexSubscriptions;
use craftpulse\ats\services\GuzzleService;
use craftpulse\ats\services\LocationService;
use craftpulse\ats\services\MapboxService;
use craftpulse\ats\services\SyncCodesService;
use craftpulse\ats\services\SyncVacanciesService;
use craftpulse\ats\services\SyncOfficesService;
use craftpulse\ats\services\SyncUsersService;
use craftpulse\ats\utilities\SyncUtility;

use Throwable;
use verbb\formie\elements\Submission;
use verbb\formie\elements\Form;

use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;

use yii\base\Event;
use yii\log\Dispatcher;
use yii\log\Logger;

/**
 * Class Ats
 *
 * @author    CraftPulse
 * @package   Ats
 * @since     1.0.0
 * @property-read MapboxService $mapbox
 * @property-read SyncOfficesService $offices
 * @property-read SyncVacanciesService $vacancies
 * @property-read SyncCodesService $codes
 * @property-read GuzzleService $guzzleService
 * @property-read PratoFlexProvider $pratoProvider
 * @property-read PratoFlexMapper $pratoMapper
 * @property-read PratoFlexSubscriptions $pratoSubscriptions
 * @property-read LocationService $locationService
 * @property-read SyncUsersService $users
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
     * @var mixed|object|null
     */
    public mixed $queue;

    /**
     * @property-read SyncVacanciesService $vacancies
     * @property-read SyncOfficesService $offices
     * @property-read SyncCodesService $codes
     * @property-read GuzzleService $guzzleService
     * @property-read PratoFlexMapper $pratoMapper
     * @property-read PratoFlexProvider $pratoProvider
     * @property-read PratoFlexSubscriptions $pratoSubscription
    */
    public static function config(): array
    {
        return [
            'components' => [
                // Guzzle Service
                'guzzleService' => GuzzleService::class,

                // Sync services
                'offices' => SyncOfficesService::class,
                'vacancies' => SyncVacanciesService::class,
                'codes' => SyncCodesService::class,
                'users' => SyncUsersService::class,

                // PratoFlex services
                // @TODO: additional, figure out a way to do this dynamically
                'pratoMapper' => PratoFlexMapper::class,
                'pratoProvider' => PratoFlexProvider::class,
                'pratoSubscriptions' => PratoFlexSubscriptions::class,

                // Other services
                'mapbox' => MapboxService::class,
                'locationService' => LocationService::class,
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

        $this->registerLogTarget();
        $this->registerUserPermissions();

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
            $this->registerUtilities();
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
     * @throws Throwable
     */
    public function log(string $message, array $params = [], int $type = Logger::LEVEL_INFO): void
    {
        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            $params['username'] = $user->username;
        }

        $encoded_params =  str_replace('\\', '', Json::encode($params));

        $message = Craft::t('ats', $message . ' ' . $encoded_params, $params);

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
     * @property Form $form
     * @return void
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
                }
            }
        );

        Event::on(
            Submission::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /** @var Submission $submission */
                /* @property-read Form $form */
                $submission = $event->sender;
                $formHandle = $submission->form->handle;
                $settings = Ats::$plugin->settings;

                if($formHandle == 'applicationForm' || $formHandle == 'applicationFormRegisteredUser') {
                    if($settings->atsProviderType === "pratoFlex") {
                        Ats::$plugin->pratoSubscriptions->createUserApplication($submission);
                    }
                }

                if($formHandle == 'spontaneousApplicationFormRegisteredUser' || $formHandle == 'spontaneousApplicationFormGuestUser') {
                    if($settings->atsProviderType === "pratoFlex") {
                        Ats::$plugin->pratoSubscriptions->createUserApplication($submission, true);
                    }
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
     * Registers utilities
     */
    private function registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
               $event->types[] = SyncUtility::class;
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
                        'ats:sync-offices' => [
                            'label' => Craft::t('ats', 'Synchronize the offices.'),
                        ],
                        'ats:sync-vacancies' => [
                            'label' => Craft::t('ats', 'Synchronize the vacancies.'),
                        ],
                        'ats:view-subscriptions' => [
                            'label' => Craft::t('ats', 'View user subscriptions.'),
                        ],
                    ]
                ];
            }
        );
    }

    /**
     * Registers a custom log target
     *
     * @see LineFormatter::SIMPLE_FORMAT
     */
    private function registerLogTarget(): void
    {
        if (Craft::getLogger()->dispatcher instanceof Dispatcher) {
            Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
               'name' => 'ats',
               'categories' => ['ats'],
               'level' => LogLevel::INFO,
               'logContext' => false,
               'allowLineBreaks' => true,
               'formatter' => new LineFormatter(
                   format: "%datetime% [%channel%.%level_name%] %message% %context%\n",
                   dateFormat: 'Y-m-d H:i:s',
               ),
            ]);
        }
    }
}
