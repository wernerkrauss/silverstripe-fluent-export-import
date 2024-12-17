<?php

namespace Netwerkstatt\FluentExIm\Tests\Stub;

use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use TractorCow\Fluent\Extension\FluentExtension;

class LocalisedDataObject extends DataObject implements TestOnly
{
    private static $table_name = 'FluentExImTest_LocalisedDataObject';

    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText',
    ];

    private static $extensions = [
        FluentExtension::class,
        AutoTranslate::class
    ];
    private bool $canEdit = true;

    public function setCanEdit(bool $canEdit)
    {
        $this->canEdit = $canEdit;
    }

    public function canEdit($member = null)
    {
        return parent::canEdit() && $this->canEdit;
    }
}
