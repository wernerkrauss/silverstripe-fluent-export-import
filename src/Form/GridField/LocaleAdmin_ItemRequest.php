<?php

namespace Netwerkstatt\FluentExIm\Form\GridField;

use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;
use TractorCow\Fluent\Model\Locale;

class LocaleAdmin_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    /**
     * @config
     */
    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm',
        'doExport',
        'ImportForm',
        'doImport'
    ];

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $exportLink = $this->Link('doExport');
        if ($this->getRecord()->canExport()) {
            $form->Actions()->push(
                LiteralField::create('doExportButton',
                    '<a class="btn btn-primary font-icon-export no-ajax" name="doExport" href="' . $exportLink . '" target="_blank">'
                    . _t(self::class . '.DOEXPORT', 'Export Translations') . '</a>')
            );
        }
        $importButtonClass = $this->getRecord()->canImport()
            ? 'btn-primary'
            : 'btn-secondary';

        if ($this->getRecord()->canImport()) {
            $importLink = $this->Link('ImportForm');
            $importWindowJs = <<<JS
            window.open('$importLink', 'print_order', 'toolbar=0,scrollbars=1,location=1,statusbar=0,menubar=0,resizable=1,width=800,height=600,left = 50,top = 50');return false;
JS;
            $form->Actions()->push(
//                LiteralField::create(
//                    'DoImport',
//                    "<button class=\"no-ajax grid-print-button btn action $importButtonClass font-icon-tags\" onclick=\"javascript:$importWindowJs\">"
//                    . _t(self::class . 'DOIMPORT', 'Import Translations') . '</button>'
//                FormAction::create('ImportForm', _t(self::class . '.DOIMPORT', 'Import Translations'))
//                    ->addExtraClass('font-icon-install'),
                $this->getImportModalAndButton()
            );
        }

        return $form;
    }

    public function doExport()
    {
        /** @var Locale $record */
        $record = $this->getRecord();
        $locale = $record->Locale;

        $fileNames = FluentExportHelper::exportAllFluentClasses($locale);
        $zipFilename = FluentExportHelper::generateZipArchive($fileNames, $locale);
        ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
    }

    public function ImportForm(): Form
    {
        $fields = FieldList::create([
            HeaderField::create('ImportHeader', _t(self::class . '.IMPORTHEADER', 'Import Translations to {locale}',
                ['locale' => $this->getRecord()->Locale])),
        ]);

        $actions = FieldList::create([
            FormAction::create('doImport', _t(self::class . '.DOIMPORT', 'Import Translations'))
        ]);

        $form = Form::create();
        $form->setFields($fields);
        $form->setActions($actions);

        return $form;
    }

    public function doImport()
    {
        return "Import";
    }

    private function getImportModalAndButton()
    {
        $modalID = $this->getRecord()->ID . '_ImportTranslationsModal';
        // Check for form message prior to rendering form (which clears session messages)
        $form = $this->ImportForm();
        $hasMessage = $form && $form->getMessage();

        // Render modal
        $template = SSViewer::get_templates_by_class(static::class, '_Modal');
        $viewer = ArrayData::create([
            'ImportModalTitle' => _t(self::class . '.IMPORTHEADER', 'Import Translations to {locale}',
                ['locale' => $this->getRecord()->Locale]),
            'ImportModalID' => $modalID,
            'ImportForm' => $form,
        ]);
        $modal = $viewer->renderWith($template)->forTemplate();

        // Build action button
        $button = new FormAction(
            'ImportForm',
            'Import Translations',
//            _t(
//                'Netwerkstatt\\SilvershopSlotableProducts\\Form\\GridField\\GenerateSlotButton.GENERATE',
//                'Generate Time Slots'
//            ),
        );
        $button
            ->addExtraClass('font-icon-install')
            ->addExtraClass('btn btn-secondary btn--icon-large action_import')
//            ->setForm($gridField->getForm())
            ->setAttribute('data-toggle', 'modal')
            ->setAttribute('aria-controls', $modalID)
            ->setAttribute('data-target', "#{$modalID}")
            ->setAttribute('data-modal', $modal);

        // If form has a message, trigger it to automatically open
        if ($hasMessage) {
            $button->setAttribute('data-state', 'open');
        }

        return $button;
        return [
            $this->targetFragment => $button->Field()
        ];
    }
}
