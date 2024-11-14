<?php

namespace Netwerkstatt\FluentExIm\Extension;

use LeKoala\CmsActions\CustomLink;
use LeKoala\CmsActions\SilverStripeIcons;
use LeKoala\PureModal\PureModal;
use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use TractorCow\Fluent\Model\Locale;

class LocaleExportImport extends Extension
{
    public function canExport(): bool
    {
        return $this->getOwner()->canView();
    }

    public function canImport(): bool
    {
        if ($this->getOwner()->IsGlobalDefault) {
            return false;
        }

        return $this->getOwner()->canView();
    }

    public function updateCMSActions(FieldList $actions)
    {
        if ($this->getOwner()->canExport()) {
            $exportAction = CustomLink::create('doExport', _t(self::class . '.DOEXPORT', 'Export Translations'));
            $exportAction->setButtonIcon(SilverStripeIcons::ICON_EXPORT);
            $exportAction->setNoAjax(true);
            $exportAction->setConfirmation(_t(self::class . '.EXPORT_CONFIRMATION', 'Export all {locale} translations as yml files in a zip archive?', ['locale' => $this->owner->Locale]));
            $actions->push($exportAction);
        }

        $buttonTitle = _t(
            self::class . '.IMPORT_MODAL_TITLE',
            'Import {locale} Translations',
            ['locale' => $this->getOwner()->Title]
        );
        $modalTitle = _t(
            self::class . '.IMPORT_MODAL_TITLE',
            'Import {locale} ({localeCode})Translations',
            ['locale' => $this->getOwner()->Title, 'localeCode' => $this->getOwner()->Locale]
        );

        $import = PureModal::create('ImportLocale', $buttonTitle, sprintf('<h1>%s</h1>', $modalTitle));
        $import->setIframeAction('ImportModal');
        //doesn't work now, see https://github.com/lekoala/silverstripe-pure-modal/issues/12
        $import->addExtraClass('font-icon font-icon-install');
        $import->setButtonIcon(SilverStripeIcons::ICON_INSTALL);

        $actions->push($import);
    }

    public function onAfterUpdateCMSActions(FieldList $actions)
    {
        $exportAction = $actions->fieldByName('doExport');
        $importAction = $actions->fieldByName('ImportLocale');
        if ($exportAction) {
            //move at the end of the stack to appear on the right side
            $actions->remove($exportAction);
            $actions->push($exportAction);
        }

        if ($importAction) {
            //move at the end of the stack to appear on the right side
            $actions->remove($importAction);
            $actions->push($importAction);
        }
    }

    public function doExport(): never
    {
        /** @var Locale $record */
        $record = $this->getOwner();
        $locale = $record->Locale;

        $fileNames = FluentExportHelper::exportAllFluentClasses($locale);
        $zipFilename = FluentExportHelper::generateZipArchive($fileNames, $locale);
        ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
        die();
    }
}
