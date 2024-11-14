<?php

namespace Netwerkstatt\FluentExIm\Extension;

use TractorCow\Fluent\Model\Locale;
use Netwerkstatt\FluentExIm\Helper\FluentImportHelper;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use Symfony\Component\Yaml\Yaml;

class LocaleAdmin extends Extension
{
    /**
     * @config
     */
    private static $allowed_actions = [
        'ImportModal',
        'ImportTranslationsForm',
        'doImport'
    ];


    public function ImportModal(HTTPRequest $request): string
    {
        return $this->renderDialog(['Form' => $this->ImportTranslationsForm($request)]);
    }

    public function ImportTranslationsForm(HTTPRequest $request): Form
    {
        $ID = $request->requestVar('ID');
        if (!$ID) {
            return $this->getOwner()->renderWith('SilverStripe\\Admin\\CMSDialog', ['Content' => 'No ID']);
        }

        $record = Locale::get()->byID($ID);
        $fields = $this->getImportFields();
        $fields->push(HiddenField::create('ID', 'ID', $ID));
        $fields->push(HiddenField::create('Locale', 'Locale', $record->Locale));

        $buttonTitle = _t(self::class . '.IMPORT_MODAL_TITLE', 'Import {locale} Translations', ['locale' => $record->Title]);
        $importAction = FormAction::create('doImport', $buttonTitle);
        $importAction->addExtraClass('btn btn-outline-danger font-icon font-icon-upload');

        $actions = FieldList::create([$importAction]);
        return Form::create($this->getOwner(), 'ImportTranslationsForm', $fields, $actions);
    }

    /**
     * Render a dialog
     *
     * @param array $customFields
     * @return string
     */
    protected function renderDialog(array $customFields = null): string
    {
        // Set empty content by default otherwise it will render the full page
        if (empty($customFields['Content'])) {
            $customFields['Content'] = '';
        }

        return $this->getOwner()->renderWith('SilverStripe\\Admin\\CMSDialog', $customFields);
    }

    public function getImportFields(): FieldList
    {
        $info = HeaderField::create('ImportInfo', _t(
            self::class . '.IMPORTINFO',
            'Import translations from yml files with the translations. The file structure should be like the one you can export.'
        ));

        $upload = FileField::create('ImportUpload', _t(self::class . '.IMPORTUPLOAD', 'Upload translations'));
        $upload->setAllowedExtensions(['yml']);

        return FieldList::create([
            $info,
            $upload
        ]);
    }

    public function doImport($data, Form $form)
    {
        if (!array_key_exists('ImportUpload', $data)) {
            $form->sessionMessage('No file uploaded', 'bad');
            return $this->getOwner()->redirectBack();
        }

        $file = $data['ImportUpload'];
        $content = file_get_contents($file['tmp_name']);
        $translationData = Yaml::parse($content);

        $translated = [];
        foreach ($translationData as $locale => $classes) {
            //check if locale exists and is locale of current object
            if ($locale !== $data['Locale']) {
                $form->sessionMessage('Locale in file does not match locale of current object', 'bad');
                return $this->getOwner()->redirectBack();
            }

            FluentImportHelper::setLocale($locale);
            foreach ($classes as $className => $records) {
                $translated[$className] = FluentImportHelper::importTranslationsForClass($className, $records);
            }
        }

        $count = 0;
        foreach ($translated as $class => $records) {
            $count += count($records);
        }

        $form->sessionMessage($count . ' Translations imported', 'good');
        return $this->getOwner()->redirectBack();
    }
}
