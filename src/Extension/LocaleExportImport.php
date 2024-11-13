<?php

namespace Netwerkstatt\FluentExIm\Extension;

use LeKoala\CmsActions\CustomAction;
use LeKoala\CmsActions\CustomLink;
use LeKoala\CmsActions\SilverStripeIcons;
use Netwerkstatt\FluentExIm\Helper\FluentExportHelper;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use TractorCow\Fluent\Model\Locale;

class LocaleExportImport extends Extension
{
    public function canExport(): bool
    {
        return $this->owner->canView();
    }

    public function canImport(): bool
    {
        if ($this->getOwner()->IsGlobalDefault) {
            return false;
        }
        return $this->owner->canView();
    }

    public function updateCMSActions(FieldList $actions)
    {
        if($this->owner->canExport()) {
            $exportAction = CustomLink::create('doExport', 'Export Translations');
            $exportAction->setButtonIcon(SilverStripeIcons::ICON_EXPORT);
            $exportAction->setNoAjax(true);
            //doesn't work with setNoAjax, see https://github.com/lekoala/silverstripe-cms-actions/issues/40
//            $exportAction->setConfirmation(_t(self::class . '.EXPORT_CONFIRMATION', 'Export all {locale} translations as yml files in a zip archive?', ['locale' => $this->owner->Locale]));
            $actions->push($exportAction);
        }

    }

    public function onAfterUpdateCMSActions(FieldList $actions)
    {
        $exportAction = $actions->fieldByName('doExport');
        if ($exportAction) {
            //move at the end of the stack to appear on the right side
            $actions->remove($exportAction);
            $actions->push($exportAction);
        }
    }

    public function doExport()
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
