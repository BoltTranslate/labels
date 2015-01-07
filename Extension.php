<?php
/**
 * Labels Extension for Bolt
 * @author Bob den Otter <bob@twokings.nl>
 */

namespace Bolt\Extension\Bolt\Labels;

require_once __DIR__ . '/include/Model.php';

use Bolt\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Extension extends \Bolt\BaseExtension
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function getName()
    {
        return "labels";
    }

    public function initialize()
    {
        $this->model = new Model($this->app);
        $this->addTwigFunction('l', 'twigL');
        $this->addTwigFunction('setLanguage', 'twigSetLanguage');

        $this->app['integritychecker']->registerExtensionTable(array($this->model, 'getTablesSchema'));

        $this->boltPath = $this->app['config']->get('general/branding/path');

        // Set the current language..
        $lang = null;

        if (!empty($_GET['lang'])) {
            // Language has been passed explicitly as ?lang=xx
            $lang = trim(strtolower($_GET['lang']));
        }
        elseif (isset($_SERVER['HTTP_HOST'])) {
            if ($extracted = $this->extractLanguage($_SERVER['HTTP_HOST'])) {
                // We're on a language-specific domain
                $lang = $extracted;
            }
        }

        if (!empty($lang) && $this->isValidLanguage($lang)) {
            $this->app['session']->set('lang', $lang);
        }

        if (is_null($this->app['session']->get('lang'))) {
            $this->app['session']->set('lang', 'nl');
        }

        $this->config['labels']['current'] = $this->app['session']->get('lang');
        $this->setCurrentLanguage($this->config['labels']['current']);

        $this->addMenuOption(__('Labels'), "$this->boltPath/translations", "icon-flag");

        $this->app->get("$this->boltPath/translations", array($this, 'translationsGET'))->bind('translations');
        $this->app->get("$this->boltPath/translations/list", array($this, 'listTranslations'))->bind('list_translations');
        $this->app->get("$this->boltPath/translations/csv", array($this, 'csvExportGET'))->bind('translations_csv_export');
        $this->app->post("$this->boltPath/translations/csv", array($this, 'csvImportPOST'))->bind('translations_csv_import');

    }

    public function extractLanguage($lang)
    {
        if (preg_match('/^([a-z]{2})\./', $lang, $matches)) {
            return $matches[1];
        }
        else {
            return false;
        }
    }

    /**
     * Validate a two-letter language code.
     */
    public function isValidLanguage($lang) {
        return preg_match('/^[a-z]{2}$/', $lang);
    }

    public function setCurrentLanguage($lang)
    {
        if ($this->isValidLanguage($lang)) {
            // Note: we're not changing the session value here, because we
            // don't want to persist this language override across requests.
            //
            // We're using the Twig global 'lang' as our source of truth, this
            // way we can change the current language from within a template by
            // setting the lang variable.
            $this->app['twig']->addGlobal('lang', $lang);
        }
    }

    public function getCurrentLanguage() {
        $twigGlobals = $this->app['twig']->getGlobals();
        if (isset($twigGlobals['lang'])) {
            return $twigGlobals['lang'];
        }
        else {
            return null;
        }
    }

    public function listTranslations(Request $request) {
        $this->requireUserPermission('labels');
        $page = intval($request->get('page'));
        $items = $this->model->getTranslatableItems('nl', $this->currentLanguage, false, $page);
        return $this->render('translatables.twig', array('items' => $items, 'sourceLanguage' => 'nl', 'destLanguage' => $this->currentLanguage));
    }

    public function translationsGET(Request $request) {
        $this->requireUserPermission('labels');
        return $this->render('import_form.twig', array());
    }

    public function csvExportGET(Request $request) {
        $this->requireUserPermission('labels');
        $csv = $this->model->getExportableItems();
        $headers = array('Content-Type' => 'text/csv');
        $response = Response::create($csv, 200, $headers);
        return $response;
    }

    public function csvImportPOST(Request $request) {
        $this->requireUserPermission('labels');
        $csvFilename = $_FILES['csv_file']['tmp_name'];
        $csvFile = fopen($csvFilename, 'r');
        $count = $this->model->importCSV($csvFile);
        fclose($csvFile);
        $this->app['session']->getFlashBag()->set('success', __("Imported %count% translations", array('%count%' => $count)));
        return redirect('translations');
    }

    /**
     * Twig function {{ l() }} in Labels extension.
     * Input can be in one of the following forms::
     *     "foo" -> namespace = '', key = 'foo', language = <default>
     *     "bar:foo" -> namespace = 'bar', key = 'foo', language = <default>
     *     "bar:foo:nl" -> namespace = 'bar', key = 'foo', language = 'nl'
     *     ":foo:nl" -> namespace = '', key = 'foo', language = 'nl'
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

    public function twigSetLanguage($lang) {
        $this->setCurrentLanguage($lang);
        return '';
    }

    public function __get($name) {
        switch ($name) {
            case 'currentLanguage':
                return $this->getCurrentLanguage();
        }
    }

    private function render($template, $data) {
        $this->app['twig.loader.filesystem']->addPath(dirname(__FILE__) . '/templates');
        $data['base_path'] = $this->boltPath . '/translations';
        return $this->app['render']->render($template, $data);
    }
}
