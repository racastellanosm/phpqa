<?php

namespace Edge\QA;

use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReportTest extends TestCase
{
    private $output;
    private $phplocXsl;
    private $xmlParams = ['bootstrap.min.css' => '','bootstrap.min.js' => '', 'jquery.min.js' => ''];

    protected function setUp(): void
    {
        $this->output = __DIR__ . "/result.html";
        $this->phplocXsl = __DIR__ . "/../../app/report/phploc.xsl";

        parent::setUp();
    }

    /**
     * @throws RuntimeError|SyntaxError|LoaderError
     */
    public function testConvertTwigToHtml()
    {
        twigToHtml("phpqa.html.twig", array('tools' => array()), $this->output);
        $this->assertNotFalse(strpos(file_get_contents($this->output), 'phpqa' ));
    }

    /** @dataProvider provideXml */
    public function testConvertXmlToHtml($xml, $assertOutput)
    {
        xmlToHtml([__DIR__ . "/{$xml}"], $this->phplocXsl, $this->output, $this->xmlParams);
        $this->assertNotFalse(strpos(file_get_contents($this->output), $assertOutput));
    }

    public function provideXml(): array
    {
        return [
            'create html' => ['phploc.xml', '</table>'],
            'create empty file if something went south' => ['invalid.xml', '']
        ];
    }

    public function testIgnoreMissingXmlDocuments()
    {
        xmlToHtml([], $this->phplocXsl, $this->output, $this->xmlParams);
        $this->assertFalse(file_exists($this->output));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->output)) {
            unlink($this->output);
        }

        parent::tearDown();
    }
}
