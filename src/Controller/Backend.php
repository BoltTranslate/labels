<?php

namespace Bolt\Extension\Bolt\Labels\Controller;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;
use Bolt\Extension\Bolt\Labels\Config;
use Bolt\Extension\Bolt\Labels\Labels;
use Bolt\Extension\Bolt\Labels\LabelsExtension;
use Bolt\Library as Lib;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Backend implements ControllerProviderInterface
{
    /** @var Config */
    private $config;

    /**
     * Constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        /** @var $ctr ControllerCollection */
        $ctr = $app['controllers_factory'];
        $ctr->value(Zone::KEY, Zone::BACKEND);

        $ctr->get('/', [$this, 'translations'])
            ->bind('labels')
        ;
        $ctr->get('/list', [$this, 'listTranslations'])
            ->bind('list_labels')
        ;
        $ctr->post('/save', [$this, 'save'])
            ->bind('save_labels')
        ;

        return $ctr;
    }

    public function before(Request $request, Application $app)
    {
        /** @var LabelsExtension $extension */
        $extension = $app['extensions']->get('Bolt/Members');
        $dir = $extension->getWebDirectory()->getPath();

        $handsCss = (new Stylesheet('/' . $dir . '/handsontable.full.min.css'))->setZone(Zone::BACKEND)->setLate(false);
        $handsJs = (new JavaScript('/' . $dir . '/handsontable.full.min.js'))->setZone(Zone::BACKEND)->setLate(true);

        $app['asset.queue.file']->add($handsCss);
        $app['asset.queue.file']->add($handsJs);

        $user   = $app['users']->getCurrentUser();
        if ($app['users']->hasRole($user['id'], 'labels')) {
            return null;
        }

        /** @var UrlGeneratorInterface $generator */
        $generator = $app['url_generator'];

        return new RedirectResponse($generator->generate('dashboard'), Response::HTTP_SEE_OTHER);
    }

    public function translations(Application $app, Request $request)
    {
        $data = [];
        $languages = array_map('strtoupper', $app['labels.config']->getLanguages());

        /** @var array $labels */
        $labels = (array) $app['labels']->getLabels();
        ksort($labels);
        foreach ($labels as $label => $row) {
            $values = [];
            foreach ($languages as $l) {
                $values[] = $row[mb_strtolower($l)] ?: '';
            }
            $data[] = array_merge([$label], $values);
        }

        $context = [
            'columns' => array_merge(['Label'], $languages),
            'data'    => $data,
        ];

        return $app['twig']->render('import_form.twig', $context);
    }

    /**
     * Save JSON file.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return RedirectResponse
     */
    public function save(Application $app, Request $request)
    {
        $arr = [];
        $columns = array_map('mb_strtolower', json_decode($request->get('columns')));
        $labels = json_decode($request->get('labels'));

        // remove the label.
        array_shift($columns);

        foreach ($labels as $labelrow) {
            $key = mb_strtolower(trim(array_shift($labelrow)));
            $values = array_combine($columns, $labelrow);
            if (!empty($key)) {
                $arr[$key] = $values;
            }
        }

        $app['labels']->saveLabels($arr);

        return Lib::redirect('labels');
    }
}
