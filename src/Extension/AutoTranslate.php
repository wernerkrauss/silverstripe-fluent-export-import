<?php

namespace Netwerkstatt\FluentExIm\Extension;

use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use Netwerkstatt\FluentExIm\Translator\ChatGPTTranslator;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AutoTranslate extends DataExtension
{
    private static $db = [
        'IsAutoTranslated' => 'Boolean',
        'LastTranslation' => 'Datetime',
    ];

    private static $field_include = [
        'IsAutoTranslated',
        //LastTranslation timestamp is used in default locale to mark a change; in other locales to remember the time of translation
        'LastTranslation',
    ];


    public function onBeforeWrite()
    {
        if ($this->hasDefaultLocale() && $this->getOwner()->isChanged()) {
            $this->getOwner()->LastTranslation = DBDatetime::now()->getValue();
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('LastTranslation');
        if ($this->hasDefaultLocale()) {
            $fields->removeByName('IsAutoTranslated');
        }
        if (!$this->hasDefaultLocale()) {
            $isAutoTranslated = $fields->dataFieldByName('IsAutoTranslated');
            if (!$isAutoTranslated) {
                $isAutoTranslated = CheckboxField::create('IsAutoTranslated');
            }
            $isAutoTranslated->setTitle($this->getOwner()->fieldLabel('IsAutoTranslated') . '; Last Translation: ' . $this->getOwner()->dbObject('LastTranslation')->Nice());
            $fields->insertAfter('Title', $isAutoTranslated);
        }
    }

    /**
     * @throws \RuntimeException
     * @throws \JsonException
     * @todo: return some status message for AIAutoTranslate task
     * @todo: currently only chatgpt is supported, make it more generic
     *
     */
    public function autoTranslate()
    {
        $this->checkIfAutoTranslateFieldsAreTranslatable();

        $owner = $this->getOwner();
        if (!$this->hasDefaultLocale()) {
            return;
        }

        $data = $this->getTranslatableFields();
        if (empty($data)) {
            return;
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        //@todo use dependency injection later
        $apiKey = Environment::getEnv('CHATGPT_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('No API Key found');
        }
        $translator = new ChatGPTTranslator($apiKey);

        foreach (Locale::get()->exclude(['Locale' => Locale::getDefault()->Locale]) as $locale) {
            $existsInLocale = $this->getOwner()->existsInLocale($locale->Locale);
            //get translated dataobject
            $translatedObject = FluentState::singleton()
                ->setLocale($locale->Locale)
                ->withState(function (FluentState $state) use ($owner) {
                    return $owner::get()->byID($owner->ID);
                });
            //if translated do is newer than original, do not translate. It is already translated
            if ($translatedObject->LastTranslation > $owner->LastEdited) {
                continue;
            }
            //if translated do is not set to auto translate, do not translate as it was edited manually
            if ($existsInLocale && !$translatedObject->IsAutoTranslated) {
                continue;
            }

            $translatedData = $translator->translate($json, $locale->Locale);
            $translatedData = json_decode($translatedData, true);

            if (!$translatedData) {
                continue;
            }

            if (!is_array($translatedData)) {
                continue;
            }

            $translatedObject->update($translatedData);
            $translatedObject->IsAutoTranslated = true;
            $translatedObject->LastTranslation = DBDatetime::now()->getValue();
            $translatedObject->write();
        }
    }


//get all fields that are translatable as array
    public function getTranslatableFields(): array
    {
        $fields = FluentHelper::getLocalisedDataFromDataObject($this->getOwner(), $this->getOwner()->Locale);
        if (array_key_exists('ID', $fields)) {
            unset($fields['ID']);
        }
        if (array_key_exists('LastTranslation', $fields)) {
            unset($fields['LastTranslation']);
        }
        unset($fields['IsAutoTranslated']);
        return $fields;
    }

    public function hasDefaultLocale()
    {
        return $this->getOwner()->Locale === Locale::getDefault()->Locale;
    }

    /**
     * Check if the required fields are configured as translated fields
     * @throws \RuntimeException
     * @return void
     */
    public function checkIfAutoTranslateFieldsAreTranslatable()
    {
        if (!$this->getOwner()->hasExtension(FluentExtension::class)) {
            throw new \RuntimeException($this->getOwner()->ClassName . ' does not have FluentExtension');
        }
        foreach (['IsAutoTranslated', 'LastTranslation'] as $field) {
            $isLocalised = false;
            foreach ($this->getOwner()->getLocalisedTables() as $localisedTable) {
                if (in_array($field, $localisedTable)) {
                    $isLocalised = true;
                }
            }
            if (!$isLocalised) {
                throw new \RuntimeException($this->getOwner()->ClassName . ' does not have ' . $field . ' as translatable field');
            }
        }
    }
}
