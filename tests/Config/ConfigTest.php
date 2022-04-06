<?php

namespace Edge\QA;

use Exception;
use PHPUnit\Framework\TestCase;

/** @SuppressWarnings(PHPMD.TooManyPublicMethods) */
class ConfigTest extends TestCase
{
    private int $defaultToolsCount = 13;

//    public function testLoadDefaultConfig()
//    {
//        $config = new Config();
//        assertThat($config->value('phpcpd.minLines'), is(greaterThan(0)));
//        assertThat($config->value('phpcpd.minTokens'), is(greaterThan(0)));
//        assertThat($config->value('phpcs.standard'), is(nonEmptyString()));
//        assertThat($config->value('phpcs.ignoreWarnings'), identicalTo(false));
//        assertThat($config->value('phpcs.reports.cli'), is(nonEmptyArray()));
//        assertThat($config->value('phpcs.reports.file'), is(nonEmptyArray()));
//        assertThat($config->value('php-cs-fixer.rules'), is(nonEmptyString()));
//        assertThat($config->value('php-cs-fixer.isDryRun'), identicalTo(true));
//        assertThat($config->value('php-cs-fixer.allowRiskyRules'), identicalTo(false));
//        assertThat($config->path('php-cs-fixer.config'), is(nullValue()));
//        assertThat($config->path('phpmetrics.config'), is(nullValue()));
//        assertThat($config->path('phpmd.standard'), is(nonEmptyString()));
//        assertThat($config->value('phpmd.ignoreParsingErrors'), is(true));
//        assertThat($config->value('phpstan.level'), identicalTo(0));
//        assertThat($config->value('phpstan.memoryLimit'), is(nullValue()));
//        assertThat($config->value('phpunit.config'), is(nullValue()));
//        assertThat($config->value('phpunit.reports.file'), is(emptyArray()));
//        assertThat($config->value('psalm.config'), is(nonEmptyString()));
//        assertThat($config->value('psalm.deadCode'), identicalTo(false));
//        assertThat($config->value('psalm.threads'), identicalTo(1));
//        assertThat($config->value('psalm.showInfo'), identicalTo(true));
//        assertThat($config->value('psalm.memoryLimit'), is(nullValue()));
//        assertThat($config->value('phpmetrics.config'), is(nullValue()));
//        assertThat($config->value('phpmetrics.junit'), is(nullValue()));
//        assertThat($config->value('phpmetrics.composer'), is(nullValue()));
//        assertThat($config->value('phpmetrics.git'), identicalTo(false));
//        assertThat($config->value('pdepend.coverageReport'), is(nullValue()));
//        assertThat($config->value('deptrac.depfile'), is(nullValue()));
//    }

    public function testBuildAbsolutePath()
    {
        $config = new Config();
        $this->assertStringStartsNotWith('app/', $config->path('phpmd.standard'));
    }

    public function testOverrideDefaultConfig()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__);
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'my-standard.xml';

        $this->assertEquals(5, $config->value('phpcpd.minLines'));
        $this->assertEquals(70, $config->value('phpcpd.minTokens'));
        $this->assertEquals('Zend', $config->value('phpcs.standard'));
        $this->assertEquals($path, $config->path('phpmd.standard'));
    }

    public function testAllowPartialUpdateOfTools()
    {
        $config = new Config();
        $this->assertNotNull($config->value('tool.phpmetrics'));
        $this->assertCount($this->defaultToolsCount, $config->value('tool'));

        $config->loadUserConfig(__DIR__);
        $this->assertCount($this->defaultToolsCount + 1, $config->value('tool'));
    }

    public function testIgnoreNonExistentUserConfig()
    {
        $directoryWithoutConfig = __DIR__ . '/../';
        $config = new Config();
        $this->shouldStopPhpqa();
        $config->loadUserConfig($directoryWithoutConfig);
    }

    public function testNoExceptionWhenCwdHasNoConfig()
    {
        $directoryWithoutConfig = __DIR__ . '/../';
        $config = new Config($directoryWithoutConfig);
        $config->loadUserConfig('');

        $this->assertEquals(5, $config->value('phpcpd.minLines'));
    }

    public function testThrowExceptionWhenFileDoesNotExist()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__);
        $this->shouldStopPhpqa();
        $config->path('phpcs.standard');
    }

    public function testConfigCsvString()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__);
        $extensions = $config->csv('phpqa.extensions');

        $this->assertEquals('php,inc,module', $extensions);
    }

    public function testIgnoreInvalidBinaryDoesNotExist()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__);

        $this->assertNull($config->getCustomBinary('phpunit'));
    }

    public function testToolAndBinaryNameMightNotMatch()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__);

        $this->assertNotNull($config->getCustomBinary('phpmetrics'));
    }

    public function testMultipleConfig()
    {
        $config = new Config();
        $config->loadUserConfig(__DIR__ . ',' . __DIR__ . '/sub-config');

        $this->assertEquals('PSR2', $config->value('phpcs.standard'));
        $this->assertEquals('my-standard.xml', $config->value('phpmd.standard'));
        $this->assertEquals(53, $config->value('phpcpd.lines'));
        $this->assertEquals('php,inc', $config->csv('phpqa.extensions'));
    }

    public function testAutodetectConfigInCurrentDirectory()
    {
        $config = new Config(__DIR__);
        $config->loadUserConfig('');
        $this->assertEquals('Zend', $config->value('phpcs.standard'));
    }

    public function testIgnoreAutodetectedConfigIfUserConfigIsSpecified()
    {
        $currentDir = __DIR__;
        $config = new Config($currentDir);
        $config->loadUserConfig("{$currentDir},{$currentDir}/sub-config,");

        $this->assertEquals('PSR2', $config->value('phpcs.standard'));
    }

    private function shouldStopPhpqa()
    {
        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException(Exception::class);
        } else {
            $this->expectException(Exception::class);
        }
    }
}
