<?php

namespace Bolt\Extension\Bolt\BoltForms\Tests;

use Bolt\Extension\Bolt\Labels\Extension;

/**
 * Ensure that the extension loads correctly.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ExtensionTest extends AbstractExtensionTest
{
    public function testExtensionRegister()
    {
        $extension = $this->getExtension();

        // Check getName() returns the correct value
        $name = $extension->getName();
        $this->assertSame($name, 'labels');
    }
}
