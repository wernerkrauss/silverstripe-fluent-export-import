<?php

namespace Netwerkstatt\FluentExIm\Extension;

use Netwerkstatt\FluentExIm\Form\GridField\LocaleAdmin_ItemRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use TractorCow\Fluent\Model\Locale;

class LocaleAdmin extends Extension
{
    public function updateGridFieldConfig(GridFieldConfig $config)
    {
//        if ($this->getOwner()->modelClass === Locale::class) {
//            $config->getComponentByType(GridFieldDetailForm::class)?->setItemRequestClass(LocaleAdmin_ItemRequest::class);
//        }
    }
}
