<?php

namespace Netwerkstatt\FluentExIm\Helper;

use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentImportHelper
{
    private static string $locale = '';

    public static function setLocale($locale): void
    {
        self::$locale = $locale;
    }

    public static function importTranslationsForClass(string $className, array $translations): array
    {
        $class = singleton($className);
        if (!$class->hasExtension(FluentExtension::class)) {
            throw new \InvalidArgumentException('Class ' . $className . ' does not have FluentExtension');
        }

        $fieldsToTranslate = FluentHelper::getTranslatedFieldsForClass($className);

        $locale = self::$locale;
        /** @var ?Locale $localeObj */
        $localeObj = Locale::get()->filter('Locale', $locale)->first();
        if ($localeObj === null || !$localeObj->exists()) {
            throw new \InvalidArgumentException('Locale ' . $locale . ' not found');
        }

        $translated = [];
        foreach ($translations as $translation) {
            /** @var ?DataObject $dataObject */
            $dataObject = DataObject::get($className)->byID($translation['ID']);
            if ($dataObject === null || !$dataObject->exists()) {
                throw new \InvalidArgumentException('DataObject of class ' . $className . ' with ID ' . $translation['ID'] . ' not found');
            }

            FluentState::singleton()
                ->setLocale($locale)
                ->withState(static function (FluentState $state) use ($fieldsToTranslate, $dataObject, $translation) {
                    foreach ($fieldsToTranslate as $key => $field) {
                        if (isset($translation[$field])) {
                            $dataObject->$field = $translation[$field];
                        }
                    }

                    $dataObject->write();
                });
            $translated[] = $dataObject;
        }

        return $translated;
    }
}
