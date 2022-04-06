<?php

namespace Edge\QA\Tools\Analyzer;

use Edge\QA\OutputMode;

class Phpstan extends \Edge\QA\Tools\Tool
{
    public static $SETTINGS = array(
        'optionSeparator' => ' ',
        'outputMode' => OutputMode::XML_CONSOLE_OUTPUT,
        'xml' => ['phpstan.xml'],
        'errorsXPath' => '//checkstyle/file/error',
        'composer' => 'phpstan/phpstan',
        'internalDependencies' => [
            'nette/neon' => 'Nette\Neon\Neon',
        ],
    );

    public function __invoke(): array
    {
        $createAbsolutePaths = function (array $relativeDirs) {
            return array_values(array_filter(array_map(
                function ($relativeDir) {
                    return '%currentWorkingDirectory%/' . trim($relativeDir, '"');
                },
                $relativeDirs
            )));
        };

        $defaultConfig = $this->config->path('phpstan.standard') ?: (getcwd() . '/phpstan.neon');
        if (file_exists($defaultConfig)) {
            $config = \Nette\Neon\Neon::decode(file_get_contents($defaultConfig));
            $config['parameters'] += [
                'excludePaths' => [],
            ];
        } else {
            $config = [
                'parameters' => [
                    'autoload_directories' => $createAbsolutePaths($this->options->getAnalyzedDirs()),
                    'excludePaths'     => [],
                ],
            ];
        }

        $config['parameters']['excludePaths'] = array_merge(
            $config['parameters']['excludePaths'],
            $createAbsolutePaths($this->options->ignore->phpstan())
        );

        $phpstanConfig = "# Configuration generated in phpqa\n" . \Nette\Neon\Neon::encode($config);
        $neonFile = $this->saveDynamicConfig($phpstanConfig, 'neon');

        $args = array(
            'analyze',
            'ansi' => '',
            $this->getErrorFormatOption() => 'checkstyle',
            'level' => $this->config->value('phpstan.level'),
            'configuration' => $neonFile,
            $this->options->getAnalyzedDirs(' '),
        );
        if ($this->config->value('phpstan.memoryLimit')) {
            $args['memory-limit'] = $this->config->value('phpstan.memoryLimit');
        }
        return $args;
    }

    private function getErrorFormatOption(): string
    {
        return $this->toolVersionIs('<', '0.10.3') ?  'errorFormat' : 'error-format';
    }
}
