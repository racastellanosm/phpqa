<?php

namespace Edge\QA;

use PHPUnit\Framework\TestCase;

/** @SuppressWarnings(PHPMD.TooManyPublicMethods) */
class OptionsTest extends TestCase
{
    // copy-pasted options from CodeAnalysisTasks
    private array $defaultOptions = [
        'analyzedDirs' => './',
        'buildDir' => 'build/',
        'ignoredDirs' => 'vendor',
        'ignoredFiles' => '',
        'tools' => 'phploc,phpcpd,phpcs,pdepend,phpmd,phpmetrics',
        'output' => 'file',
        'config' => '',
        'verbose' => true,
        'report' => false,
        'execution' => 'parallel',
    ];

    private Options $fileOutput;

    protected function setUp(): void
    {
        $this->fileOutput = $this->overrideOptions();

        parent::setUp();
    }

    private function overrideOptions(array $options = []): Options
    {
        return new Options(array_merge($this->defaultOptions, $options));
    }

    public function testEscapePaths()
    {
        $this->assertEquals('"./"', $this->fileOutput->getAnalyzedDirs(','));
        $this->assertEquals('"build//file"', $this->fileOutput->toFile('file'));
        $this->assertEquals('build//file', $this->fileOutput->rawFile('file'));
    }

    public function testRespectToolsOrderDefinedInOption()
    {
        $cliOutput = $this->overrideOptions(['output' => 'cli', 'tools' => 'phpunit,phpmetrics']);
        $tools = $this->buildRunningTools($cliOutput, ['phpmetrics' => [], 'phpunit' => []]);
        $this->assertEquals(['phpunit', 'phpmetrics'], array_keys($tools));
    }

    public function testIgnorePdependInCliOutput()
    {
        $cliOutput = $this->overrideOptions(array('output' => 'cli'));

        $this->assertNotEmpty($this->buildRunningTools($this->fileOutput, ['pdepend' => []]));
        $this->assertEmpty($this->buildRunningTools($cliOutput, ['pdepend' => []]));
    }

    /** @dataProvider provideInternalClass */
    public function testIsSuggestedToolInstalled(array $classes, $isInstalled)
    {
        $tools = $this->buildRunningTools($this->fileOutput, ['pdepend' => $classes]);

        $this->assertEquals($isInstalled, $tools['pdepend']->isExecutable);
    }

    public function provideInternalClass(): array
    {
        return [
            'internal class is available' => [
                ['internalClass' => 'UnknownTool\UnknownClass'],
                false
            ],
            'at least one internal class is available' => [
                ['internalClass' => ['UnknownTool\UnknownClass', __CLASS__]],
                true
            ],
            'dependency is available' => [
                ['internalClass' => __CLASS__, 'internalDependencies' => ['package' => __CLASS__]],
                true
            ],
            'dependency is not available' => [
                ['internalClass' => __CLASS__, 'internalDependencies' => ['package' => 'UnknownTool\UnknownClass']],
                false
            ],
        ];
    }

    /** @dataProvider provideOutputs */
    public function testBuildOutput(array $opts, $isSavedToFiles, $isOutputPrinted, $hasReport)
    {
        $options = $this->overrideOptions($opts);

        $this->assertEquals($isSavedToFiles, $options->isSavedToFiles);
        $this->assertEquals($isOutputPrinted, $options->isOutputPrinted);
        $this->assertEquals($hasReport, $options->hasReport);
    }

    public function provideOutputs(): array
    {
        return [
            'ignore verbose and report in CLI output' => [
                ['output' => 'cli', 'verbose' => false, 'report' => true],
                false,
                true,
                false
            ],
            'respect verbose mode and report in FILE output' => [
                ['output' => 'file', 'verbose' => false, 'report' => true],
                true,
                false,
                true
            ]
        ];
    }

    /** @dataProvider provideExecutionMode */
    public function testExecute(array $opts, $isParallel)
    {
        $options = $this->overrideOptions($opts);
        $this->assertEquals($isParallel, $options->isParallel);
    }

    public function provideExecutionMode(): array
    {
        return [
            'parallel execution is default mode' => [[], true],
            'parallel execution is enabled' => [['execution' => 'parallel'], true],
            'dont use parallelism if execution is other word' => [['execution' => 'single'], false]
        ];
    }

    /** @dataProvider provideAnalyzedDir */
    public function testBuildRootPath($analyzedDirs, $expectedRoot)
    {
        $options = $this->overrideOptions(array('analyzedDirs' => $analyzedDirs));
        $this->assertEquals($expectedRoot, $options->getCommonRootPath());
    }

    public function provideAnalyzedDir(): array
    {
        $dirSeparator = DIRECTORY_SEPARATOR;
        return [
            'current dir + analyzed dir + slash' => ['src', getcwd() . "{$dirSeparator}src{$dirSeparator}"],
            'find common root from multiple dirs' => ['src,tests', getcwd() . $dirSeparator],
            'no path when dir is invalid' => ['./non-existent-directory', ''],
            'file directory + skip invalid dir' => ['./non-existent-directory,phpqa', getcwd() . $dirSeparator],
        ];
    }

    public function testLoadAllowedErrorsCount()
    {
        $options = $this->overrideOptions(['tools' => 'phpcs:1,pdepend']);
        $tools = $this->buildRunningTools($options, ['phpcs' => [], 'pdepend' => []]);


        $this->assertEquals(1, $tools['phpcs']->getAllowedErrorsCount());
        $this->assertNull($tools['pdepend']->getAllowedErrorsCount());
    }

    private function buildRunningTools(Options $o, array $tools): array
    {
        foreach (array_keys($tools) as $tool) {
            $tools[$tool] += [
                'hasCustomBinary' => false,
                'runBinary' => 'irrelevant',
            ];
        }
        return $o->buildRunningTools($tools);
    }
}
