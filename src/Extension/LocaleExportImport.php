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
