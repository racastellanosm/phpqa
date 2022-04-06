<?php

namespace Edge\QA;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** @SuppressWarnings(PHPMD.TooManyPublicMethods) */
class RunningToolTest extends TestCase
{

    private int $errorsCountInXmlFile = 2;

    public function testBuildOptionWithDefinedSeparator()
    {
        $tool = new RunningTool('tool', ['optionSeparator' => ' ']);

        $this->assertEquals('--option', $tool->buildOption('option', ''));
        $this->assertEquals('--option value', $tool->buildOption('option', 'value'));
        $this->assertEquals('--option 0', $tool->buildOption('option', 0));
    }

    public function testMarkSuccessWhenXPathIsNotDefined()
    {
        $tool = new RunningTool('tool', ['errorsXPath' => null]);
        $this->assertEquals([true, ''], $tool->analyzeResult());
    }

    /** @dataProvider provideAllowedErrorsForNonexistentFile */
    public function testMarkFailureWhenXmlFileDoesNotExist($allowedErrors, $expectedIsOk)
    {
        $tool = new RunningTool('tool', [
            'xml' => ['non-existent.xml'],
            'errorsXPath' => '//errors/error',
            'allowedErrorsCount' => $allowedErrors,
        ]);
        list($isOk, $error) = $tool->analyzeResult();

        $this->assertEquals($expectedIsOk, $isOk);
        $this->assertStringContainsString('not found', $error);
    }

    public function provideAllowedErrorsForNonexistentFile(): array
    {
        return [
            'success when allowed errors are not defined' => [null, true],
            'success when errors count are defined' => [$this->errorsCountInXmlFile, false],
        ];
    }

    /** @dataProvider provideAllowedErrors */
    public function testCompareAllowedCountWithErrorsCountFromXml($allowedErrors, $isOk)
    {
        $tool = new RunningTool('tool', [
            'xml' => ['tests/Error/errors.xml'],
            'errorsXPath' => '//errors/error',
            'allowedErrorsCount' => $allowedErrors
        ]);

        $this->assertEquals([$isOk, $this->errorsCountInXmlFile], $tool->analyzeResult());
    }

    public function provideAllowedErrors(): array
    {
        return [
            'success when allowed errors are not defined' => [null, true],
            'success when errors count <= allowed count' => [$this->errorsCountInXmlFile, true],
            'failure when errors count > allowed count' => [$this->errorsCountInXmlFile - 1, false],
        ];
    }

    public function testRuntimeSelectionOfErrorXpath()
    {
        $tool = new RunningTool('tool', [
            'xml' => ['tests/Error/errors.xml'],
            'errorsXPath' => [
                false => '//errors/error',
                true => '//errors/error[@severity="error"]',
            ],
            'allowedErrorsCount' => 0,
        ]);

        $tool->errorsType = true;
        $this->assertEquals([false, 1], $tool->analyzeResult());
    }

    /** @dataProvider provideMultipleXpaths */
    public function testMultipleXpaths(array $xpaths, array $expectedResult)
    {
        $tool = new RunningTool('tool', [
            'xml' => ['tests/Error/errors.xml'],
            'errorsXPath' => [
                null => $xpaths,
            ],
            'allowedErrorsCount' => 3,
        ]);

        $this->assertEquals($expectedResult, $tool->analyzeResult());
    }

    public function provideMultipleXpaths(): array
    {
        return [
            'multiple elements' => [['//errors/error', '//errors/warning'], [true, 2 + 1]],
            'invalid xpath' => [[null], [false, 'SimpleXMLElement::xpath(): Invalid expression']],
        ];
    }

    /** @dataProvider provideProcess */
    public function testAnalyzeExitCodeInCliMode($allowedErrors, $exitCode, array $expectedResult)
    {
        $tool = new RunningTool('tool', [
            'allowedErrorsCount' => $allowedErrors
        ]);

        $tool->process = $this->createMock(Process::class);
        $tool->process->expects($this->once())->method('getExitCode')->willReturn($exitCode);

        $this->assertEquals($expectedResult, $tool->analyzeResult(true));
    }

    public function provideProcess(): array
    {
        return [
            'success when exit code = 0' => [0, 0, [true, 0]],
            'success when exit code <= allowed code' => [1, 1, [true, 1]],
            'failure when errors count > allowed count but errors count is always one' => [0, 2, [false, 1]],
        ];
    }

    public function testCreateUniqueIdForUserReport()
    {
        $tool = new RunningTool('phpcs', []);
        $tool->userReports['dir/path.php'] = 'My report';
        $report = $tool->getHtmlRootReports()[0];

        $this->assertEquals('phpcs-dir-path-php', $report['id']);
    }
}
