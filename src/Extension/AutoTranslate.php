<?php

namespace Netwerkstatt\FluentExIm\Extension;

use LeKoala\CmsActions\SilverStripeIcons;
use LeKoala\PureModal\PureModal;
use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use Netwerkstatt\FluentExIm\Translator\AITranslationStatus;
use Netwerkstatt\FluentExIm\Translator\ChatGPTTranslator;
use Netwerkstatt\FluentExIm\Translator\Translatable;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AutoTranslate extends DataExtension
{
    /**
     * @config
     */
    private static $db = [
        'IsAutoTranslated' => 'Boolean',
        'LastTranslation' => 'Datetime',
    ];

    /**
     * @config
     */
    private static $field_include = [
        'IsAutoTranslated',
        //LastTranslation timestamp is used in default locale to mark a change; in other locales to remember the time of translation
        'LastTranslation',
    ];

    private static ?Translatable $translator = null;

    public function canTranslate(): bool
    {
        return $this->hasDefaultLocale() && $this->getOwner()->canEdit();
    }


    public function onBeforeWrite()
    {
        if ($this->getOwner()->Locale && $this->hasDefaultLocale() && $this->getOwner()->isChanged()) {
            $this->getOwner()->LastTranslation = DBDatetime::now()->getValue();
        }
    }

    public function updateCMSFields(FieldList $fields): void
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

            if ($this->getOwner()->LastTranslation) {
                $lastTranslation = DBDateTime::create()->setValue($this->getOwner()->LastTranslation);
                $isAutoTranslated->setTitle($this->getOwner()->fieldLabel('IsAutoTranslated') . '; Last Translation: ' . $lastTranslation->Nice());
                $fields->insertAfter('Title', $isAutoTranslated);
            }
        }
    }

    public function updateCMSActions(FieldList $actions)
    {
        if (!$this->getOwner()->canTranslate()) {
            return;
        }

        $translatableLocales = Locale::get()->exclude(['IsGlobalDefault' => 1]);
        $localesCount = $translatableLocales->count();
        $localesString = implode(', ', $translatableLocales->column('Title'));
        $translateconfirmation = _t(
            self::class . '.TRANSLATE_CONFIRMATION',
            'Translate to 1 other locale ({locales})?|Translate to {count} other locales ({locales})?',
            ['count' => $localesCount, 'locales' => $localesString]
        );


        $buttonTitle = _t(
            self::class . '.TRANSLATE_MODAL_TITLE',
            'Auto Translate'
        );
        $modalTitle = _t(
            self::class . '.TRANSLATE_MODAL_TITLE',
            'Import {locale} ({localeCode})Translations',
            ['locale' => $this->getOwner()->Title, 'localeCode' => $this->getOwner()->Locale]
        );
        $url = Controller::join_links([
            '/aitranslate/',
            '?ClassName=' . $this->getOwner()->ClassName,
            '?ID=' . $this->getOwner()->ID,
        ]);

        $translate = PureModal::create('doAutoTranslate', $buttonTitle, sprintf('<h1>%s</h1>', $buttonTitle));
        $translate->setIframe($url);
        $translate->setButtonIcon(SilverStripeIcons::ICON_TRANSLATABLE);

        $actions->push($translate);
    }

    public function onAfterUpdateCMSActions(FieldList $actions)
    {
        $translateAction = $actions->fieldByName('doTranslate');
        if ($translateAction) {
            //move at the end of the stack to appear on the right side
            $actions->remove($translateAction);
            $actions->push($translateAction);
        }
    }

    /**
     * @param $data
     * @param $form
     * @return AITranslationStatus[]
     * @throws \JsonException
     */
    public function doRecursiveAutoTranslate($data, $form): array
    {
        $doPublish = $data['doPublish'] ?? false;
        $forceTranslation = $data['forceTranslation'] ?? false;
        //@todo ability to filter locales to translate to
        $status[] = $this->autoTranslate($doPublish, $forceTranslation);
        $ownedObjects = $this->getOwner()->findRelatedObjects('owns', true);
        foreach ($ownedObjects as $ownedObject) {
            if (!$ownedObject->hasExtension(AutoTranslate::class)) {
                continue;
            }
            $status[] = $ownedObject->autoTranslate($doPublish, $forceTranslation);
        }

        return $status;
    }


    /**
     * @throws \RuntimeException
     * @throws \JsonException
     * @todo: currently only chatgpt is supported, make it more generic
     *
     */
    public function autoTranslate(bool $doPublish = false, bool $forceTranslation = false): AITranslationStatus
    {
        $this->checkIfAutoTranslateFieldsAreTranslatable();
        $status = new AITranslationStatus($this->getOwner());

        /** @var DataObject $owner */
        $owner = $this->getOwner();
        if (!$this->hasDefaultLocale()) {
            return $status->setStatus(AITranslationStatus::STATUS_ERROR)->setMessage(AITranslationStatus::ERRORMSG_NOTDEFAULTLOCALE);
        }

        $data = $this->getTranslatableFields();
        if ($data === []) {
            return $status->setStatus(AITranslationStatus::STATUS_ERROR)->setMessage(AITranslationStatus::ERRORMSG_NOTHINGFOUND);
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $translator = self::getTranslator();

        foreach (Locale::get()->exclude(['Locale' => Locale::getDefault()->Locale]) as $locale) {
            $status = FluentState::singleton()
                ->withState(function (FluentState $state) use (
                    $locale,
                    $translator,
                    $status,
                    $json,
                    $doPublish,
                    $forceTranslation
                ) {
                    $state->setLocale($locale->Locale);
                    return $this->performTranslation(
                        $translator,
                        $status,
                        $locale,
                        $json,
                        $doPublish,
                        $forceTranslation
                    );
                });
        }
        return $status;
    }


    /**
     * get all fields that are translatable
     * @return array
     */
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

    public function hasDefaultLocale(): bool
    {
        return $this->getOwner()->Locale === Locale::getDefault()->Locale;
    }

    /**
     * Check if the required fields are configured as translated fields
     * @return void
     * @throws \RuntimeException
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

    /**
     * When this module is added to an existing project, the LastTranslation field is not set for existing objects.
     * If not set, the object will be translated all the time, as LastEdited is not localised.
     *
     * @return DataObject
     */
    public function fixLastTranslationForDefaultLocale(): DataObject
    {
        $owner = $this->getOwner();
        if ($this->hasDefaultLocale() && $owner->LastTranslation === null) {
            $owner->LastTranslation = $owner->LastEdited;
            $owner->write();
            if ($owner->hasExtension(Versioned::class) && $owner->isPublished()) {
                /** @var Versioned|DataObject $owner */
                $owner->publishSingle();
            }
        }

        return $owner;
    }

    private function performTranslation(
        Translatable $translator,
        AITranslationStatus $status,
        Locale $locale,
        false|string $json,
        bool $doPublish = false,
        bool $forceTranslation = false
    ): AITranslationStatus {
        $owner = $this->getOwner();
        $existsInLocale = $owner->existsInLocale($locale->Locale);
        //get translated dataobject
        /** @var DataObject $translatedObject */
        $translatedObject = DataObject::get($owner->ClassName)->byID($owner->ID);

        //if translated do is newer than original, do not translate. It is already translated
        if ($existsInLocale && $translatedObject->LastTranslation > $owner->LastTranslation && !$forceTranslation) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ALREADYTRANSLATED);
            return $status;
        }

        //if translated do is not set to auto translate, do not translate as it was edited manually
        if ($existsInLocale && !$translatedObject->IsAutoTranslated) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_NOTAUTOTRANSLATED);
            return $status;
        }

        $translatedDataOrig = $translator->translate($json, $locale->Locale);
        $translatedData = json_decode($translatedDataOrig, true);

        if (!$translatedData) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_NOTHINGTOTRANSLATE);
            return $status;
        }

        if (!is_array($translatedData)) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ERROR);
            $status->setSource($json);
            $status->setAiResponse($translatedDataOrig);
            $status->setData($translatedData);
            return $status;
        }

        $translatedObject->update($translatedData);
        $translatedObject->IsAutoTranslated = true;
        $translatedObject->LastTranslation = DBDatetime::now()->getValue();
        $translatedObject->write();

        $isPublishableObject = $translatedObject->hasExtension(Versioned::class) && $owner->hasExtension(FluentVersionedExtension::class);
        $ownerIsPublished = $isPublishableObject && $owner->isPublishedInLocale($owner->Locale);

        if ($doPublish && $isPublishableObject && $ownerIsPublished) {
            /** @var Versioned|DataObject $translatedObject */
            $translatedObject->publishSingle();
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_PUBLISHED);
        } else {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_TRANSLATED);
        }
        return $status;
    }

    public static function getTranslator(): Translatable
    {
        if (!self::$translator) {
            self::$translator = self::getDefaultTranslator();
        }

        return self::$translator;
    }

    public static function setTranslator(Translatable $translator): void
    {
        self::$translator = $translator;
    }

    /**
     * Fallback if no translator is set. Use ChatGPT for now
     *
     * @throws \RuntimeException
     * @return Translatable
     */
    public static function getDefaultTranslator(): Translatable
    {
        //@todo use dependency injection later
        $apiKey = Environment::getEnv('CHATGPT_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('No API Key found');
        }

        self::$translator = new ChatGPTTranslator($apiKey);
        return self::$translator;
    }
}
