<?php

namespace Netwerkstatt\FluentExIm\Helper;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentImportHelper
{
    private static string $locale = '';
    private static bool $should_publish = false;

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

                    if (self::$should_publish &&  $dataObject->hasExtension(Versioned::class)) {
                        /** @var Versioned|DataObject $dataObject */
                        $dataObject->publishSingle();
                    }
                });
            $translated[] = $dataObject;
        }

        return $translated;
    }

    public static function validateLocaleTranslationData(array $translationData): bool
    {
        if (self::$locale === '') {
            throw new \RuntimeException('Locale must be set before importing translations');
        }

        foreach (array_keys($translationData) as $locale) {
            //check if locale exists and is locale of current object
            if ($locale !== self::$locale) {
                throw new \RuntimeException(sprintf('Locale %s in file does not match import locale %s', $locale, self::$locale));
            }
        }

        return true;
    }

    public static function setShouldPublish(bool $shouldPublish)
    {
        self::$should_publish = $shouldPublish;
    }
}
