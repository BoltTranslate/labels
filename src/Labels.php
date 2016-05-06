<?php

namespace Bolt\Extension\Bolt\Labels;

use Bolt\Filesystem\Exception\FileNotFoundException;
use Bolt\Filesystem\FilesystemInterface;
use Bolt\Filesystem\Handler\JsonFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Labels
{
    /** @var Config */
    private $config;
    /** @var Session */
    private $session;
    /** @var string */
    private $filesystemManager;
    /** @var string */
    private $extBasePath;

    /**
     * Constructor.
     *
     * @param Config              $config
     * @param SessionInterface    $session
     * @param FilesystemInterface $filesystemManager
     * @param string              $extBasePath
     */
    public function __construct(Config $config, SessionInterface $session, FilesystemInterface $filesystemManager, $extBasePath)
    {
        $this->config = $config;
        $this->session = $session;
        $this->filesystemManager = $filesystemManager;
        $this->extBasePath = $extBasePath;
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        $labels = [];

        // Check that the user's JSON file exists, else copy in the default
        if (!$this->filesystemManager->has('config://extensions/labels.json')) {
            $distPath = $this->filesystemManager->getFile('extensions://' . $this->extBasePath)->getPath();
            $this->filesystemManager->copy(
                    'extensions://' . $distPath . '/files/labels.json',
                    'config://extensions/labels.json'
                );
            try {
            } catch (IOException $e) {
                $this->session->getFlashBag()->set(
                    'error',
                    'The labels file at <tt>app/config/extensions/labels.json</tt> does not exist, and can not be created. Changes can NOT be saved until you fix this.'
                );
            }
        }

        // Check the file is writable
        try {
            $this->filesystemManager->get('config://extensions/labels.json');
        } catch (IOException $e) {
            $this->session->getFlashBag()->set(
                'error',
                'The labels file at <tt>app/config/extensions/labels.json</tt> is not writable. Changes can NOT saved, until you fix this.'
            );
        }

        // Read the contents of the file
        try {
            $finder = $this->filesystemManager
                ->find()
                ->files()
                ->name('labels.json')
                ->in($this->filesystemManager->getDir('config://extensions')->getFullPath())
            ;

            /** @var \Bolt\Filesystem\Handler\File $file */
            foreach ($finder->files() as $file) {
                $labels = json_decode($file->read(), true);
                continue;
            }
        } catch (\Exception $e) {
            $this->session->getFlashBag()->set(
                'error',
                sprintf('There was an issue loading the labels: %s', $e->getMessage())
            );
        }

        return $labels;
    }

    /**
     * Add a label to the file, if it was found in the template. If the file isn't
     * writable, fail silently, because the user will get notified when they
     * go the screen to edit the translations.
     *
     * @param string $label
     */
    public function addLabel($label)
    {
        $label = mb_strtolower(trim($label));
        $languages = $this->config->getLanguages();

        /** @var JsonFile $labelsFile */
        $labelsFile = $this->filesystemManager->get('config://extensions/labels.json', new JsonFile());
        try {
            $jsonArray = $labelsFile->parse();
        } catch (FileNotFoundException $e) {
            $jsonArray = [];
        }

        $jsonArray[$label] = array_fill_keys($languages, '');
        ksort($jsonArray);

        try {
            $labelsFile->dump($jsonArray);
        } catch (IOException $e) {
            // Computer says "No!"
        }
    }

    /**
     * Save the labels to the JSON file.
     *
     * @param array $jsonString
     */
    public function saveLabels(array $jsonString)
    {
        $jsonArray = json_encode($jsonString, JSON_PRETTY_PRINT);
        if (strlen($jsonArray) < 50) {
            $this->session->getFlashBag()->set(
                'error',
                'There was an issue encoding the file. Changes were NOT saved.'
            );
        }

        try {
            $this->filesystemManager->update('config://extensions/labels.json', $jsonArray);
            $this->session->getFlashBag()->set('success', 'Changes to the labels have been saved.');
        } catch (IOException $e) {
            $this->session->getFlashBag()->set(
                'error',
                'The labels file at <tt>../app/config/extensions/labels.json</tt> is not writable. Changes were NOT saved.'
            );
        }
    }

    /**
     * Validates if the 2-letter language code was actually set in the extension config
     *
     * @param $lang
     *
     * @return bool
     */
    public function isAllowedLanguage($lang)
    {
        if (in_array($lang, $this->config->getLanguages())) {
            return true;
        }

        return false;
    }
}
