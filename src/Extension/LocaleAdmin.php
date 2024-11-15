<?php

namespace Netwerkstatt\FluentExIm\Extension;

use Netwerkstatt\FluentExIm\Helper\FluentImportHelper;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use Symfony\Component\Yaml\Yaml;
use TractorCow\Fluent\Model\Locale;

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

        $buttonTitle = _t(
            self::class . '.IMPORT_MODAL_TITLE',
            'Import {locale} Translations',
            ['locale' => $record->Title]
        );
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
            'Import translations from a zip containing yml files or a single yml file with the translations. The file structure should be like the one you can export.'
        ));

        $upload = FileField::create('ImportUpload', _t(self::class . '.IMPORTUPLOAD', 'Upload translations'));
        $upload->setAllowedExtensions(['yml', 'zip']);

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

        $count = 0;
        if (str_ends_with((string) $file['name'], '.yml')) {
            $content = file_get_contents($file['tmp_name']);

            try {
                $count = $this->handleYmlFile($content, $data['Locale']);
            } catch (\Exception $e) {
                $form->sessionMessage($e->getMessage(), 'bad');
                return $this->getOwner()->redirectBack();
            }
        }

        if (str_ends_with((string) $file['name'], '.zip')) {
            $zip = new \ZipArchive();
            $res = $zip->open($file['tmp_name']);
            $errorMessages = [];
            if ($res === true) {
                $count = 0;
                for ($i = 0; $i < $zip->numFiles; ++$i) {
                    $filename = $zip->getNameIndex($i);
                    $content = $zip->getFromName($filename);
                    try {
                        $count += $this->handleYmlFile($content, $data['Locale']);
                    } catch (\Exception $e) {
                        $errorMessages[$filename] = $e->getMessage();

                    }
                }

                if (count($errorMessages) > 0) {
                    $message = 'Some files could not be imported: '
                        .implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($errorMessages), $errorMessages));
                    $form->sessionMessage($message, 'bad');
                    return $this->getOwner()->redirectBack();
                }


                $zip->close();
            } else {
                $form->sessionMessage('Error opening zip file', 'bad');
                return $this->getOwner()->redirectBack();
            }
        }

        $form->sessionMessage($count . ' Translations imported', 'good');
        return $this->getOwner()->redirectBack();
    }

    public function handleYmlFile(string $content, string $locale): int
    {
        $translationData = Yaml::parse($content);
        FluentImportHelper::setLocale($locale);
        FluentImportHelper::validateLocaleTranslationData($translationData);
        $translated = [];
        foreach ($translationData as $classes) {
            foreach ($classes as $className => $records) {
                $translated[$className] = FluentImportHelper::importTranslationsForClass($className, $records);
            }
        }

        $count = 0;
        foreach ($translated as $class => $records) {
            $count += count($records);
        }

        return $count;
    }
}
