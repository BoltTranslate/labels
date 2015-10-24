<?php

namespace Bolt\Extension\Bolt\BoltForms\Tests;

use Bolt\Tests\BoltUnitTest;
use Bolt\Extension\Bolt\Labels\Extension;

/**
 * Base class for Labels extension testing.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class AbstractExtensionTest extends BoltUnitTest
{
    /** \Bolt\Application */
    protected $app;

    protected function getApp($boot = true)
    {
        if ($this->app) {
            return $this->app;
        }

        $app = parent::getApp($boot);
        $extension = new Extension($app);

        $app['extensions']->register($extension);

        return $this->app = $app;
    }

    protected function getExtension()
    {
        if ($this->app === null) {
            $this->getApp();
        }

        return $this->app['extensions.labels'];
    }
}
