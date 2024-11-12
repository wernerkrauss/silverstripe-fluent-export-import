<?php

namespace Netwerkstatt\FluentExIm\Form\GridField;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class ImportTranslationsButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler
{
    use Injectable;

    public function getActions($gridField)
    {
        return [
            'importTranslations',
            'LocaleImportForm'
        ];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'importTranslations') {
            return $this->handleImport($gridField, $gridField->getRequest());
        }

        return null;
    }

    private function handleExport(GridField $gridField, HTTPRequest $getRequest)
    {



    }

    public function getURLHandlers($gridField)
    {
        return [
            'importTranslations' => 'handleImport',
        ];
    }

    public function getHTMLFragments($gridField)
    {
        $modalID = $gridField->ID() . '_LocaleImportModal';
        // Check for form message prior to rendering form (which clears session messages)
        $form = $this->LocaleImportForm($gridField);
        $hasMessage = $form && $form->getMessage();

        // Render modal
        $template = SSViewer::get_templates_by_class(static::class, '_Modal');
        $viewer = new ArrayData([
            'ImportModalTitle' => _t(
                'Netwerkstatt\\FluentExIm\\Form\\GridField\\.TITLE',
                'Import Translations'
            ),
            'ImportModalID' => $modalID,
            'ImportForm' => $form,
        ]);
        $modal = $viewer->renderWith($template)->forTemplate();

        // Build action button
        $button = new GridField_FormAction(
            $gridField,
            'generate',
            _t(
                'Netwerkstatt\\SilvershopSlotableProducts\\Form\\GridField\\GenerateSlotButton.GENERATE',
                'Generate Time Slots'
            ),
            'generateslots',
            null
        );
        $button
            ->addExtraClass('btn btn-secondary font-icon-rocket btn--icon-large action_import')
            ->setForm($gridField->getForm())
            ->setAttribute('data-toggle', 'modal')
            ->setAttribute('aria-controls', $modalID)
            ->setAttribute('data-target', "#{$modalID}")
            ->setAttribute('data-modal', $modal);

        // If form has a message, trigger it to automatically open
        if ($hasMessage) {
            $button->setAttribute('data-state', 'open');
        }

        return [
            $this->targetFragment => $button->Field()
        ];
    }

    public function LocaleImportForm(GridField $gridField):Form
    {
    }
}
