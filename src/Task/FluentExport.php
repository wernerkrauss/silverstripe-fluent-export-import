<?php

namespace Netwerkstatt\FluentExIm\Task;

use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use TractorCow\Fluent\Model\Locale;

class FluentExport extends BuildTask
{
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
    protected $title = 'Fluent Export to YML';

    /**
     * @config
     */
    protected $description = 'Export all classes with FluentExtension to yml files';


    /**
     * @inheritDoc
     */
    public function run($request): void
    {
        $locale = Locale::getDefault()->Locale;

        $filenames = FluentExportHelper::exportAllFluentClasses($locale);

        if ($filenames == []) {
            DB::alteration_message('No classes with FluentExtension found');
            return;
        }

        $zipFilename = FluentExportHelper::generateZipArchive($filenames);

        if (Director::is_cli()) {
            echo 'Exported ' . count($filenames) . ' classes to yml files:' . PHP_EOL;
            foreach ($filenames as $key => $filename) {
                echo $filename . PHP_EOL;
            }

            echo 'Zip file created: ' . $zipFilename . PHP_EOL;
            return;
        }

        ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
    }

}
