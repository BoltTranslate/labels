<?php

namespace Bolt\Extension\Bolt\Labels;

use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Labels Extension for Bolt
 *
 * @author Bob den Otter <bob@twokings.nl>
 */
class LabelsExtension extends SimpleExtension
{
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
            function ($app) {
                return new Controller\Backend($app['labels.config']);
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        $app = $this->getContainer();

        return [
            'extend/labels' => $app['labels.controller.backend'],
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
            (new MenuEntry('labels', 'labels'))
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
     *
     * @return null
     */
    public function before(Request $request, Application $app)
    {
        if (!Zone::isFrontend($request)) {
            return null;
        }

        $lang = $request->query->get('lang');

        if (!$lang && $app['labels']->isAllowedLanguage($this->extractLanguage($request->getHost()))) {
            $lang = $this->extractLanguage($request->getHost());
        }

        if (!$lang && $app['labels']->isAllowedLanguage(substr($request->getRequestUri(), 1, 2))) {
            $lang = substr($request->getRequestUri(), 1, 2);
        }

        if (!$this->isValidLanguage($lang) && $app['labels']->isAllowedLanguage(substr($request->getLocale(), 0, 2))) {
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
     * @return \Twig_Markup
     */
    public function twigL($label, $lang = false)
    {
        $app = $this->getContainer();
        $label = $app['labels']->cleanLabel($label);

        if (!$this->isValidLanguage($lang)) {
            $lang = $this->getCurrentLanguage();
        }

        $labels = $app['labels']->getLabels();
        if (!empty($labels[$label][mb_strtolower($lang)])) {
            $res = $labels[$label][mb_strtolower($lang)];
        } else {
            $app = $this->getContainer();
            // Only show marked labels for logged in users
            if ($app['users']->getCurrentUser()) {
                $res = '<mark>' . $label . '</mark>';
            } else {
                $res = $label;
            }

            // Perhaps use the fallback?
            $default = mb_strtolower($app['labels.config']->getDefault());
            if ($app['labels.config']->isUseFallback() && !empty($labels[$label][$default])
            ) {
                $res = $labels[$label][$default];
            }

            // perhaps add it to the labels file?
            if ($app['labels.config']->isAddMissing() && empty($labels[$label])) {
                $app['labels']->addLabel($label);
            }
        }

        return new \Twig_Markup($res, 'UTF-8');
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
        } else {
            return false;
        }
    }

    /**
     * Validate a two-letter language code.
     *
     * @param string $lang
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
     * We're using the Twig global 'lang' as our source of truth, this
     * way we can change the current language from within a template by
     * setting the lang variable.
     *
     * @param string $lang
     */
    private function setCurrentLanguage($lang)
    {
        if ($this->isValidLanguage($lang)) {
            $app = $this->getContainer();
            $app['twig']->addGlobal('lang', $lang);
        }
    }

    /**
     * Get the in-use language code.
     *
     * @return string|null
     */
    private function getCurrentLanguage()
    {
        $app = $this->getContainer();
        $twigGlobals = $app['twig']->getGlobals();
        if (isset($twigGlobals['lang'])) {
            return $twigGlobals['lang'];
        } else {
            return null;
        }
    }
}
