<?php

namespace Netwerkstatt\FluentExIm\Task;

use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Symfony\Component\Yaml\Yaml;
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
        //get all classes with FluentExtension
        $classes = FluentHelper::getFluentClasses();
        $filenames = [];

        foreach ($classes as $key => $className) {
            if (count(FluentHelper::getTranslatedFieldsForClass($className)) === 0) {
                continue;
            }

            $filenames[$className] = $this->exportClass($className);
        }

        if ($filenames !== []) {
//            DB::alteration_message('Exported ' . count($filenames) . ' classes to yml files:');
//            foreach ($filenames as $key => $filename) {
//                DB::alteration_message($filename);
//            }
        } else {
            DB::alteration_message('No classes with FluentExtension found');
            return;
        }

        $filenames = array_filter($filenames); //remove empty entries

        //zip all files and offer download
        $zip = new \ZipArchive();
        $zipFilename = TEMP_PATH . '/fluent-ex-im/fluent-export.zip';
        $zip->open($zipFilename, \ZipArchive::CREATE);
        foreach ($filenames as $key => $filename) {
            $zip->addFile($filename, basename($filename));
        }

        $zip->close();
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

    private function exportClass(string $className): string
    {
        //create data array for each record of $className with ID and translatable fields
        $data = [];
        $locale = Locale::getDefault()->Locale;
        $records = $className::get()
            ->filter(['ClassName' => $className]);
        if ($records->count() === 0) {
            return '';
        }

        $data[$locale][$className] = [];
//        $data[$locale][$className]['Info'] = [
//            'Title' => singleton($className)->singular_name(),
//            'Records' => $records->count()
//        ];

        foreach ($records as $record) {
            $recordData = FluentHelper::getLocalisedDataFromDataObject($record, $locale);
            if ($recordData === []) {
                continue;
            }

            $data[$locale][$className][$record->ID] = $recordData;
        }

        if ($data[$locale][$className] === []) {
            return '';
        }

        $sanitisedClassName = str_replace('\\', '-', $className);

        return $this->createYmlFile($sanitisedClassName, $data, $locale);
    }

    private function createYmlFile(string $className, array $data, string $locale): string
    {
        $yaml = YAML::dump($data, 10, 2, YAML::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $dir = TEMP_PATH . '/fluent-ex-im/';
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = $dir . $className . '_' . $locale . '.yml';
        file_put_contents($filename, $yaml);
        return $filename;
    }
}
