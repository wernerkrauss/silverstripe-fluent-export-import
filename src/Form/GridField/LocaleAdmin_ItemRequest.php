<?php

namespace Netwerkstatt\FluentExIm\Form\GridField;

use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\LiteralField;
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
        'doExport'
    ];

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $exportLink = $this->Link('doExport');
        $form->Actions()->push(
            LiteralField::create('doExportButton',
                '<a class="btn btn-primary font-icon-export no-ajax" name="doExport" href="' . $exportLink . '" target="_blank">'
                . _t(self::class . '.EXPORT', 'Export Translations') . '</a>')
        );

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
}
