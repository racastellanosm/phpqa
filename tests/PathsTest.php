<?php

namespace Edge\QA;

use PHPUnit\Framework\TestCase;

class PathsTest extends TestCase
{
    public function testPathToBinaryIsEscaped()
    {
        define('COMPOSER_BINARY_DIR', '/home/user with space/phpqa/vendor/bin');
        $tool = 'irrelevant';

        $this->assertEquals('', buildToolBinary($tool, 'not-installed-tool'));
    }
}
