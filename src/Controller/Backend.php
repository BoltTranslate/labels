<?php

namespace Bolt\Extension\Bolt\Labels\Controller;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Collection\Bag;
use Bolt\Collection\MutableBag;
use Bolt\Common\Exception\ParseException;
use Bolt\Common\Json;
use Bolt\Controller\Backend\BackendBase;
use Bolt\Controller\Zone;
use Bolt\Extension\Bolt\Labels\Config;
use Bolt\Extension\Bolt\Labels\Labels;
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
        /** @var Labels $labels */
        $labels = $this->app['labels'];
        /** @var Config $config */
        $config = $this->app['labels.config'];
        $languages = $config->getLanguages()->mutable();
        $data = [];

        /** @var MutableBag $savedLabels */
        $savedLabels = $labels->getLabels()->sortKeys();
        foreach ($savedLabels->keys() as $label) {
            // First column is the label itself
            $values = [$label];
            foreach ($languages as $lang) {
                $values[] = $savedLabels->getPath("$label/$lang");
            }
            $data[] = $values;
        }
        // Make column titles
        $columns = $languages->map(function ($k, $v) { return strtoupper($v); });
        // Add the primary column title
        $columns->prepend('Label');

        $context = [
            'columns' => $columns,
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
        /** @var Labels $labels */
        $labels = $this->app['labels'];
        $arr = [];
        try {
            $columns = array_map('mb_strtolower', Json::parse($request->request->get('columns')));
            $rows = Json::parse($request->request->get('labels'));
        } catch (ParseException $e) {
            $this->flashes()->error(sprintf('Unable to save labels: %s', $e->getMessage()));

            return new RedirectResponse($this->generateUrl('labels'));
        }

        // remove the label.
        array_shift($columns);

        foreach ($rows as $row) {
            $key = $labels->cleanLabel(array_shift($row));
            if ($key !== '') {
                $arr[$key] = Bag::combine($columns, $row);
            }
        }

        // Commit to the JSON file
        $labels->saveLabels($arr);

        return new RedirectResponse($this->generateUrl('labels'));
    }
}
