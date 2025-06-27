<?php

namespace Netwerkstatt\FluentExIm\Task;

use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use TractorCow\Fluent\Model\Locale;

class FluentExport extends BuildTask
{
    protected static string $commandName = 'FluentExport';
    /**
     * @config
     */
    private static $segment = 'fluent-export';


    /**
     * @config
     */
    private static $is_enabled = true;

    protected $enabled = true;

    /**
     * @config
     */
    protected string $title = 'Fluent Export to YML';

    /**
     * @config
     */
    protected static string $description = 'Export all classes with FluentExtension to yml files';


    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $locale = Locale::getDefault()->Locale;

        $filenames = FluentExportHelper::exportAllFluentClasses($locale);

        if ($filenames == []) {
            $output->writeln('<error>No classes with FluentExtension found</error>');
            return 0;
        }

        $zipFilename = FluentExportHelper::generateZipArchive($filenames, $locale);

        if (Director::is_cli()) {
            $output->writeln('');
            $output->writeln('<info>Exported ' . count($filenames) . ' classes to yml files:</info>');
            $output->startList();
            foreach ($filenames as $key => $filename) {
                $output->writeListItem($filename );
            }
            $output->stopList();

            $output->writeln('<info>Zip file created in: </info><comment>' . $zipFilename . '</comment>');
            return Command::SUCCESS;
        }

        ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
        return Command::SUCCESS;
    }
}
