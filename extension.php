<?php
/**
 * Labels Extension for Bolt
 * @author Bob den Otter <bob@twokings.nl>
 */

namespace Labels;

class Extension extends \Bolt\BaseExtension
{
    public function info()
    {

        $data = array(
            'name' => "Labels",
            'description' => "This extension allows you to use translatable labels for your site. While it does not allow for fully multilingual sites, you can easily translate labels and short snippets of text to different languages. Usage: {{ l('Click here') }}",
            'author' => "Bob den Otter",
            'link' => "http://twokings.nl",
            'version' => "0.2",
            'required_bolt_version' => "0.8.5",
            'highest_bolt_version' => "1.4.5",
            'type' => "General",
            'first_releasedate' => "2012-12-12",
            'latest_releasedate' => "2012-12-12",
            'dependencies' => "",
            'priority' => 10
        );

        return $data;

    }

    public static function getAvailableLanguageCodes() {
        return array('en', 'nl', 'de', 'fy', 'fr');
    }

    public function initialize()
    {
        $this->addTwigFunction('l', 'twigL');

        // TODO: sessions don't get carried over for subdomains.

        // Set the current language..
        $languages = self::getAvailableLanguageCodes();
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

        if (!empty($lang)) {
            // Only allow whitelisted languages
            if (in_array($lang, $languages)) {
                $this->app['session']->set('lang', $lang);
            }
        }

        if (is_null($this->app['session']->get('lang'))) {
            $this->app['session']->set('lang', 'nl');
        }

        $this->config['labels']['current'] = $this->app['session']->get('lang');
        $this->app['twig']->addGlobal('lang', $this->config['labels']['current']);

        // Haal de labels op.
        $lang = $this->config['labels']['current'];
        if (in_array($lang, $languages) && preg_match('/^[a-z]*$/', $lang)) {
            $sql = "SELECT grouping, label, " . $this->config['labels']['current'] . " FROM bolt_labels";
            $stmt = $this->app['db']->prepare($sql);
            $stmt->execute();

            while ($row = $stmt->fetch()) {
                $this->labelscache[ $row['grouping'] . ":" . $row['label'] ] = $row[$this->config['labels']['current']];
            }
        }

        // Ugly hack..
        $GLOBALS['labelscache'] = $this->labelscache;
    }

    /**
     * Twig function {{ l() }} in Labels extension.
     */
    public function twigL($label="")
    {
        // Ugly hack..
        if (empty($this->labelscache)) {
            $this->labelscache = $GLOBALS['labelscache'];
        }

        if (strpos($label, ':') !== false) {
            list($grouping, $label) = explode(":", trim($label));
        } else {
            $grouping = "";
        }

        $orglabel = $label;
        $label = strtolower(safeString(strip_tags(trim($label))));
        $grouping = strtolower(safeString($grouping));

        // See if we've retrieved this before..
        if (!empty($this->labelscache[$grouping.":".$label])) {
            $res = $this->labelscache[$grouping.":".$label];
        } elseif (!isset($this->labelscache[$grouping.":".$label])) {
            // No result. Insert a blank row..
            $values = array('grouping' => $grouping, 'label' => $label, 'en' => $orglabel, 'nl' => $orglabel, 'de' => $orglabel, 'fr' => $orglabel, 'fy' => $orglabel);
            $this->app['db']->insert('bolt_labels', $values);
            $res = $orglabel;
        } else {
            // It's present, but
            $res = $orglabel;
        }

        return new \Twig_Markup($res, 'UTF-8');
    }
}
