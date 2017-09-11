<?php

namespace Bolt\Extension\Bolt\Labels\Controller;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Backend\BackendBase;
use Bolt\Controller\Zone;
use Bolt\Extension\Bolt\Labels\LabelsExtension;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class Backend extends BackendBase
{
    /**
     * {@inheritdoc}
     */
    protected function addRoutes(ControllerCollection $ctr)
    {
        $ctr->value(Zone::KEY, Zone::BACKEND);

        $ctr->get('/', [$this, 'translations'])
            ->bind('labels')
            ->before([$this, 'before'])
        ;
        $ctr->get('/list', [$this, 'listTranslations'])
            ->bind('list_labels')
        ;
        $ctr->post('/save', [$this, 'save'])
            ->bind('save_labels')
        ;

        return $ctr;
    }

    /**
     * {@inheritdoc}
     */
    public function before(Request $request, Application $app, $roleRoute = null)
    {
        /** @var LabelsExtension $extension */
        $extension = $app['extensions']->get('Bolt/Labels');
        $dir = $extension->getWebDirectory()->getPath();

        $handsonCss = (new Stylesheet('/' . $dir . '/handsontable.full.min.css'))->setZone(Zone::BACKEND)->setLate(false);
        $handsonJs = (new JavaScript('/' . $dir . '/handsontable.full.min.js'))->setZone(Zone::BACKEND)->setLate(true);
        $underscoreJs = (new JavaScript('/' . $dir . '/underscore-min.js'))->setZone(Zone::BACKEND)->setLate(true);

        $app['asset.queue.file']->add($handsonCss);
        $app['asset.queue.file']->add($handsonJs);
        $app['asset.queue.file']->add($underscoreJs);

        return parent::before($request, $app, 'labels');
    }

    /**
     * View & edit label translations.
     *
     * @return mixed
     */
    public function translations()
    {
        $data = [];
        $languages = array_map('strtoupper', $this->app['labels.config']->getLanguages());

        /** @var array $labels */
        $labels = (array) $this->app['labels']->getLabels();
        ksort($labels);
        foreach ($labels as $label => $row) {
            $values = [];
            foreach ($languages as $l) {
                $values[] = isset($row[mb_strtolower($l)]) ? $row[mb_strtolower($l)] : '';
            }
            $data[] = array_merge([$label], $values);
        }

        $context = [
            'columns' => array_merge(['Label'], $languages),
            'data'    => $data,
        ];

        /** @deprecated Should be updated to not use globals in Bolt 4 (i.e. templates need to use context.variable) */
        return $this->render('import_form.twig', [], $context);
    }

    /**
     * Save JSON file.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function save(Request $request)
    {
        $labels = $this->app['labels'];
        $arr = [];
        $columns = array_map('mb_strtolower', json_decode($request->get('columns')));
        $rows = json_decode($request->get('labels'));

        // remove the label.
        array_shift($columns);

        foreach ($rows as $row) {
            $key = $labels->cleanLabel(array_shift($row));
            $values = array_combine($columns, $row);
            if (!empty($key)) {
                $arr[$key] = $values;
            }
        }

        $labels->saveLabels($arr);

        return new RedirectResponse($this->generateUrl('labels'));
    }
}
