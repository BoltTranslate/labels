<?php

namespace Bolt\Extension\Bolt\Labels;

use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Bolt\Version;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Twig\Markup;

/**
 * Labels Extension for Bolt
 *
 * @author Bob den Otter <bob@twokings.nl>
 */
class LabelsExtension extends SimpleExtension
{
    /** @var string */
    private $currentLanguage;

    protected function registerServices(Application $app)
    {
        $app['labels'] = $app->share(
            function ($app) {
                return new Labels(
                    $app['labels.config'],
                    $app['session'],
                    $app['filesystem'],
                    $this->getBaseDirectory()->getPath()
                );
            }
        );

        $app['labels.config'] = $app->share(
            function () {
                return new Config($this->getConfig());
            }
        );

        $app['labels.controller.backend'] = $app->share(
            function () {
                return new Controller\Backend();
            }
        );

        $app->before([$this, 'before']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'l'           => 'twigL',
            'setlanguage' => 'twigSetLanguage',
            'getlanguage' => 'twigGetLanguage',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        $app = $this->getContainer();

        $baseUrl = Version::compare('3.2.999', '<')
            ? '/extensions/labels'
            : '/extend/labels'
        ;

        return [
            $baseUrl => $app['labels.controller.backend'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {
        $app = $this->getContainer();

        if (!$app['labels.config']->isShowMenu()) {
            return [];
        }

        return [
            (new MenuEntry('labels'))
                ->setRoute('labels')
                ->setLabel('Label translations')
                ->setIcon('fa:flag')
                ->setPermission('labels'),
        ];
    }

    /**
     * Set the current language, always based on a 2-letter code
     *
     * This handles the following situations:
     *
     * 1. Set the lang param via the request: example.org?lang=nl
     * 2. If the host name part of the FQDN, e.g. nl.example.org, matches one of the configured languages set the
     * language to "nl"
     * 3. If the routes are prefixed by a language code; example.org/nl/pages
     * 4. Attempts to fetch a valid lang from the locale, "nl_NL" becomes "nl"
     * 5. Uses the default that was set in the config file of this extension
     *
     * Whatever value was set, it can always be overridden in s twig template {% set lang = 'de' %}
     *
     * @param Request     $request
     * @param Application $app
     */
    public function before(Request $request, Application $app)
    {
        if (!Zone::isFrontend($request)) {
            return;
        }

        $lang = $request->query->get('lang');
        $labels = $app['labels'];

        if (!$lang && $labels->isAllowedLanguage($this->extractLanguage($request->getHost()))) {
            $lang = $this->extractLanguage($request->getHost());
        }

        if (!$lang && $labels->isAllowedLanguage(substr($request->getRequestUri(), 1, 2))) {
            $lang = substr($request->getRequestUri(), 1, 2);
        }

        if (!$this->isValidLanguage($lang) && $labels->isAllowedLanguage(substr($request->getLocale(), 0, 2))) {
            $lang = substr($request->getLocale(), 0, 2);
        }

        if (!$this->isValidLanguage($lang)) {
            $config = $this->getConfig();
            $lang = $config['default'];
        }

        if ($app['session']->isStarted()) {
            $app['session']->set('lang', $lang);
        }

        $this->setCurrentLanguage($lang);
    }

    /**
     * Twig function {{ l() }} in Labels extension.
     *
     * @param string  $label
     * @param boolean $lang
     *
     * @return Markup
     */
    public function twigL($label, $lang = false)
    {
        $app = $this->getContainer();
        /** @var Config $config */
        $config = $app['labels.config'];
        /** @var Labels $labels */
        $labels = $app['labels'];
        $label = $labels->cleanLabel($label);
        $lang = mb_strtolower($lang);

        if (!$this->isValidLanguage($lang)) {
            $lang = $this->getCurrentLanguage();
        }
        $savedLabels = $labels->getLabels();
        $savedLabel = $savedLabels->getPath("$label/$lang");

        // If we've got a live one, send it packing!
        if (!empty($savedLabel)) {
            return new Markup($savedLabel, 'UTF-8');
        }

        // If we're automatically saving new/missing labels, add it to the JSON file
        if ($config->isAddMissing() && !$savedLabels->hasItem($label)) {
            $labels->addLabel($label);
        }

        // Use the fallback if configured & exists
        $defaultLang = mb_strtolower($config->getDefault());
        $savedDefault = $savedLabels->getPath("$label/$defaultLang");
        if ($config->isUseFallback() && !empty($savedDefault)) {
            $label = $savedDefault;
        }

        // Show marked labels for logged in users
        if ($app['users']->getCurrentUser()) {
            return new Markup('<mark>' . $label . '</mark>', 'UTF-8');
        }

        return new Markup($label, 'UTF-8');
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function twigSetLanguage($lang)
    {
        $this->setCurrentLanguage($lang);

        return '';
    }

    /**
     * @return string
     */
     public function twigGetLanguage()
     {
         return (string) $this->getCurrentLanguage();
     }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'show_menu'    => true,
            'languages'    => ['en'],
            'default'      => 'en',
            'add_missing'  => true,
            'use_fallback' => true,
        ];
    }

    /**
     * Extract a language code from a string.
     *
     * @param string $lang
     *
     * @return bool|string
     */
    private function extractLanguage($lang)
    {
        $matches = [];
        if (preg_match('/^([a-z]{2})\./', $lang, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Validate a two-letter language code.
     *
     * @param string $lang
     *
     * @return int
     */
    private function isValidLanguage($lang)
    {
        return preg_match('/^[a-z]{2}$/', $lang);
    }

    /**
     * Set the in-use language code as a Twig global.
     *
     * Note: we're not changing the session value here, because we
     * don't want to persist this language override across requests.
     *
     * @param string $lang
     */
    private function setCurrentLanguage($lang)
    {
        if ($this->isValidLanguage($lang)) {
            $this->currentLanguage = $lang;
        }
    }

    /**
     * Get the in-use language code.
     *
     * @return string|null
     */
    private function getCurrentLanguage()
    {
        return $this->currentLanguage;
    }
}
