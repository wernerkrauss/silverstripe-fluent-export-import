<?php

namespace Netwerkstatt\FluentExIm\Helper;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentHelper
{
    public static function getFluentClasses(): array
    {
        return ClassInfo::classesWithExtension(FluentExtension::class, DataObject::class);
    }

    public static function getTranslatedFieldsForClass(string $className)
    {
        $class = singleton($className);
        if (!$class->hasExtension(FluentExtension::class)) {
            throw new \InvalidArgumentException('Class ' . $className . ' does not have FluentExtension');
        }

        $fields = $class->getLocalisedTables();
        $fieldsToTranslate = [];
        foreach ($fields as $fieldArray) {
            $fieldsToTranslate = array_merge($fieldsToTranslate, $fieldArray);
        }

        $ignoredFields = Config::inst()->get($className, 'translate_ignore');

        if ($ignoredFields) {
            $fieldsToTranslate = array_diff($fieldsToTranslate, $ignoredFields);
        }

        return $fieldsToTranslate;
    }

    public static function getLocalisedDataFromDataObject(DataObject $dataObject, string $locale = null):array
    {
        if ($locale === null) {
            $locale = Locale::getDefault()->Locale;
        }

        $localisedData = ['ID' => $dataObject->ID];
        $fields = self::getTranslatedFieldsForClass($dataObject::class);

        FluentState::singleton()
            ->setLocale($locale)
            ->withState(static function (FluentState $state) use ($fields, $dataObject, &$localisedData) {
                //reload Dataobject to get the correct values
                $dataObject = DataObject::get($dataObject->ClassName)->byID($dataObject->ID);
                foreach ($fields as $key => $field) {
                    $value = $dataObject->$field;
                    if ($value !== null) {
                        $localisedData[$field] = $value;
                    }
                }
            });

        if (count($localisedData) === 1 && array_key_exists('ID', $localisedData)) {
            return [];
        }

        return $localisedData;
    }
}
