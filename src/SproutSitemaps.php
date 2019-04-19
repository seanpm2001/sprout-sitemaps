<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutsitemaps;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbase\migrations\Install;
use barrelstrength\sproutbase\SproutBaseHelper;
use barrelstrength\sproutbasefields\SproutBaseFieldsHelper;
use barrelstrength\sproutbasesitemaps\SproutBaseSitemaps;
use barrelstrength\sproutbasesitemaps\SproutBaseSitemapsHelper;
use barrelstrength\sproutbaseuris\SproutBaseUrisHelper;
use barrelstrength\sproutbasesitemaps\models\Settings;
use Craft;
use craft\base\Plugin;
use craft\db\Query;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yii\base\Event;
use yii\db\Migration;

/**
 *
 * @property mixed $cpNavItem
 * @property array $cpUrlRules
 * @property array $userPermissions
 * @property array $siteUrlRules
 */
class SproutSitemaps extends Plugin
{
    use BaseSproutTrait;

    /**
     * Identify our plugin for BaseSproutTrait
     *
     * @var string
     */
    public static $pluginHandle = 'sprout-sitemaps';

    /**
     * @var bool
     */
    public $hasCpSection = true;

    /**
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * @var string
     */
    public $schemaVersion = '1.0.1';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        SproutBaseHelper::registerModule();
        SproutBaseFieldsHelper::registerModule();
        SproutBaseSitemapsHelper::registerModule();
        SproutBaseUrisHelper::registerModule();

        Craft::setAlias('@sproutsitemaps', $this->getBasePath());

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getCpUrlRules());
        });

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getSiteUrlRules());
        });

        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions['Sprout Sitemaps'] = $this->getUserPermissions();
        });
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();

        // Query the db directly because the SproutBaseRedirects Yii module may not yet be available
        $pluginSettings = (new Query())
            ->select('settings')
            ->from('{{%sproutbase_settings}}')
            ->where([
                'model' => Settings::class
            ])
            ->scalar();

        $settings = json_decode($pluginSettings, true);

        // Allow user to override plugin name in sidebar
        if (isset($settings['pluginNameOverride']) && $settings['pluginNameOverride']) {
            $parent['label'] = $settings['pluginNameOverride'];
        }


        if (Craft::$app->getUser()->checkPermission('sproutSitemaps-editSitemaps')) {
            $parent['subnav']['sitemaps'] = [
                'label' => Craft::t('sprout-sitemaps', 'Sitemaps'),
                'url' => 'sprout-sitemaps/sitemaps'
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin()) {
            $parent['subnav']['settings'] = [
                'label' => Craft::t('sprout-sitemaps', 'Settings'),
                'url' => 'sprout-sitemaps/settings'
            ];
        }

        return $parent;
    }

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
//    public function getSettings()
//    {
//        $settings = SproutBaseSitemaps::$app->sitemaps->getSitemapsSettings();
//
//        return $settings;
//    }

    /**
     * @return string|null
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml()
    {
        return \Craft::$app->getView()->renderTemplate('sprout-sitemaps/settings', [
            'settings' => $this->getSettings()
        ]);
    }

    /**
     * @return array
     */
    private function getCpUrlRules(): array
    {
        return [
            // Sitemaps
            '<pluginHandle:sprout-sitemaps>/sitemaps/edit/<sitemapSectionId:\d+>/<siteHandle:.*>' =>
                'sprout-base-sitemaps/sitemaps/sitemap-edit-template',
            '<pluginHandle:sprout-sitemaps>/sitemaps/new/<siteHandle:.*>' =>
                'sprout-base-sitemaps/sitemaps/sitemap-edit-template',
            '<pluginHandle:sprout-sitemaps>/sitemaps/<siteHandle:.*>' =>
                'sprout-base-sitemaps/sitemaps/sitemap-index-template',
            '<pluginHandle:sprout-sitemaps>/sitemaps' =>
                'sprout-base-sitemaps/sitemaps/sitemap-index-template',

            // Settings
            'sprout-sitemaps/settings/<settingsSectionHandle:.*>' => [
                'route' => 'sprout/settings/edit-settings',
                'params' => [
                    'sproutBaseSettingsType' => Settings::class
                ]
            ],
            'sprout-sitemaps/settings' => [
                'route' => 'sprout/settings/edit-settings',
                'params' => [
                    'sproutBaseSettingsType' => Settings::class
                ]
            ]
        ];
    }

    /**
     * Match dynamic sitemap URLs
     *
     * Example matches include:
     *
     * Sitemap Index Page
     * - sitemap.xml
     *
     * URL-Enabled Sections
     * - sitemap-t6PLT5o43IFG-1.xml
     * - sitemap-t6PLT5o43IFG-2.xml
     *
     * Special Groupings
     * - sitemap-singles.xml
     * - sitemap-custom-pages.xml
     *
     * @return array
     */
    private function getSiteUrlRules(): array
    {
        $settings = $this->getSettings();
        if ($settings->enableDynamicSitemaps) {
            return [
                'sitemap-<sitemapKey:.*>-<pageNumber:\d+>.xml' =>
                    'sprout-base-sitemaps/xml-sitemap/render-xml-sitemap',
                'sitemap-?<sitemapKey:.*>.xml' =>
                    'sprout-base-sitemaps/xml-sitemap/render-xml-sitemap',
            ];
        }

        return [];
    }

    /**
     * @return array
     */
    public function getUserPermissions(): array
    {
        return [
            // We need this permission on top of the accessplugin- permission
            // so that we can support the matching permission in Sprout SEO
            'sproutSitemaps-editSitemaps' => [
                'label' => Craft::t('sprout-sitemaps', 'Edit Sitemaps')
            ],
        ];
    }
}
