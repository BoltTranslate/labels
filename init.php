<?php

namespace Bolt\Extension\Bolt\Labels;

if (isset($app)) {
    $app['extensions']->register(new Extension($app));
}
