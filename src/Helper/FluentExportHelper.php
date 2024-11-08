<?php

namespace Netwerkstatt\FluentExIm\Helper;

use Symfony\Component\Yaml\Yaml;

class FluentExportHelper
{

    public static function exportClass(string $className, string $locale): string
    {
        //create data array for each record of $className with ID and translatable fields
        $data = [];

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

        return self::createYmlFile($sanitisedClassName, $data, $locale);
    }

    public static function createYmlFile(string $className, array $data, string $locale): string
    {
        $yaml = YAML::dump($data, 10, 2, YAML::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $dir = TEMP_PATH . '/fluent-ex-im/';
        if (!file_exists($dir) && (!mkdir($dir, 0777, true) && !is_dir($dir))) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $filename = $dir . $className . '_' . $locale . '.yml';
        file_put_contents($filename, $yaml);
        return $filename;
    }

    /**
     * @return array
     */
    public static function exportAllFluentClasses(string $locale): array
    {
        //get all classes with FluentExtension
        $classes = FluentHelper::getFluentClasses();
        $filenames = [];

        foreach ($classes as $key => $className) {
            if (count(FluentHelper::getTranslatedFieldsForClass($className)) === 0) {
                continue;
            }

            $filenames[$className] = FluentExportHelper::exportClass($className, $locale);
        }

         //remove empty entries
        return array_filter($filenames);
    }

    /**
     * @param array $filenames
     * @return string
     */
    public static function generateZipArchive(array $filenames, string $locale): string
    {
        $filename = sprintf('fluent-export-%s-%s.zip', $locale, date('Y-m-d-H-i-s'));
        $zipFilename = TEMP_PATH . '/fluent-ex-im/' . $filename;

        $zip = new \ZipArchive();

        $zip->open($zipFilename, \ZipArchive::CREATE);
        foreach ($filenames as $key => $filename) {
            $zip->addFile($filename, basename((string)$filename));
        }

        $zip->close();

        return $zipFilename;
    }
}
