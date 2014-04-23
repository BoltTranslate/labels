<?php
/**
 * Labels Extension for Bolt
 * @author Bob den Otter <bob@twokings.nl>
 */

namespace Labels;

require_once __DIR__ . '/include/Model.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Extension extends \Bolt\BaseExtension
{
    public function info()
    {

        $data = array(
            'name' => "Labels",
            'description' => "This extension allows you to use translatable labels for your site. While it does not allow for fully multilingual sites, you can easily translate labels and short snippets of text to different languages. Usage: {{ l('Click here') }}",
            'author' => "Bob den Otter",
            'link' => "http://twokings.nl",
            'version' => "1.9",
            'required_bolt_version' => "1.5",
            'highest_bolt_version' => "1.9",
            'type' => "General",
            'first_releasedate' => "2012-12-12",
            'latest_releasedate' => "2014-04-24",
            'dependencies' => "",
            'priority' => 10
        );

        return $data;

    }

    public function initialize()
    {
        $this->model = new Model($this->app);
        $this->addTwigFunction('l', 'twigL');

        $this->app['integritychecker']->registerExtensionTable(array($this->model, 'getTablesSchema'));

        // Set the current language..
        $lang = null;

        if (!empty($_GET['lang'])) {
            // Language has been passed explicitly as ?lang=xx
            $lang = trim(strtolower($_GET['lang']));
        }
        elseif (isset($_SERVER['HTTP_HOST'])) {
            if (preg_match('/^([a-z]{2})\./', $_SERVER['HTTP_HOST'], $matches)) {
                // We're on a language-specific domain
                $lang = $matches[1];
            }
        }

        if (!empty($lang) && preg_match('/^[a-z]{2}$/', $lang)) {
            // Only allow two-letter language codes
            $this->app['session']->set('lang', $lang);
        }

        if (is_null($this->app['session']->get('lang'))) {
            $this->app['session']->set('lang', 'nl');
        }

        $this->config['labels']['current'] = $this->app['session']->get('lang');
        $this->app['twig']->addGlobal('lang', $this->config['labels']['current']);
        $this->currentLanguage = $this->config['labels']['current'];

        $this->app->get('/bolt/translations', array($this, 'listTranslations'))->bind('translations');
    }

    public function listTranslations(Request $request) {
        $page = intval($request->get('page'));
        $items = $this->model->getTranslatableItems('nl', $this->currentLanguage, false, $page);
        return $this->render('translatables.twig', array('items' => $items, 'sourceLanguage' => 'nl', 'destLanguage' => $this->currentLanguage));
    }

    /**
     * Twig function {{ l() }} in Labels extension.
     */
    public function twigL($label="")
    {
        $labelParts = explode(':', $label);
        $language = $this->currentLanguage;
        switch (count($labelParts)) {
            case 0:
                return ''; // no label given
            case 1:
                $namespace = "";
                $label = $labelParts[0];
                break;
            case 2:
                list($namespace, $label) = $labelParts;
                break;
            default:
                list($namespace, $label, $language) = $labelParts;
                break;
        }

        $res = $this->model->getTranslation($namespace, $label, $language);

        return new \Twig_Markup($res, 'UTF-8');
    }

    private function render($template, $data) {
        $this->app['twig.loader.filesystem']->addPath(dirname(__FILE__) . '/templates');
        return $this->app['render']->render($template, $data);
    }
}
