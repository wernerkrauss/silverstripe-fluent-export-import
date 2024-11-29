<?php

namespace Netwerkstatt\FluentExIm\Extension;

use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use Netwerkstatt\FluentExIm\Translator\ChatGPTTranslator;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AutoTranslate extends DataExtensionExtension
{
    private static $db = [
        'IsAutoTranslated' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.Main', CheckboxField::create('AutoTranslated', 'Auto-Translate'));
    }

    public function autoTranslate()
    {
        $owner = $this->getOwner();
        if (!$owner->Locale === Locale::getDefault()->Locale) {
            return;
        }
        if (!$owner->isChanged()) {
            return;
        }
        $data = $this->getTranslatableFields();
        $json = json_encode($data);

        //@todo use dependency injection later
        $apiKey = Environment::getEnv('CHATGPT_API_KEY');
        if (!$apiKey) {
            throw new \Exception('No API Key found');
        }
        $translator = new ChatGPTTranslator($apiKey);

        foreach (Locale::get()->exclude(['Locale' => Locale::getDefault()->Locale]) as $locale) {
            //get translated dataobject
            $translatedObject = FluentState::singleton()
                ->setLocale($locale)
                ->withState(function (FluentState $state) use ($owner) {
                    return $owner::get()->byID($owner->ID);
                });
            //if translated do is newer than original, do not translate
            if ($translatedObject->LastEdited > $owner->LastEdited) {
                continue;
            }
            //if translated do is not set to auto translate, do not translate
            if (!$translatedObject->IsAutoTranslated) {
                continue;
            }
            $translatedData = $translator->translate($json, $locale->Locale);
            $translatedData = json_decode($translatedData, true);
            if (!$translatedData) {
                continue;
            }
            $translatedObject->update($translatedData);
            $translatedObject->IsAutoTranslated = true;
            $translatedObject->write();
        }
    }


//get all fields that are translatable as array
    public
    function getTranslatableFields(): array
    {
        $fields = FluentHelper::getLocalisedDataFromDataObject($this->getOwner(), $this->getOwner()->Locale);
        if (array_key_exists('ID', $fields)) {
            unset($fields['ID']);
        }
        return $fields;
    }
}
