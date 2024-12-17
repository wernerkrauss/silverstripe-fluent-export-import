<?php

use Netwerkstatt\FluentExIm\Tests\Stub\LocalisedDataObject;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AutoTranslateTest extends SapphireTest
{
    protected static $fixture_file = 'AutoTranslateTest.yml';
    protected static $extra_dataobjects = [
        LocalisedDataObject::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Locale::clearCached();
    }

    public function testCanTranslateIsTrueInDefaultLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertTrue((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should be default locale');
            $this->assertTrue($dataObject->canTranslate(), 'We\'re only allowed to translate in default locale');
        });
    }

    public function testCanTranslateIsFalseInOtherLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertFalse((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should not be default locale');
            $this->assertFalse($dataObject->canTranslate(), 'We\'re not allowed to translate in other locales');
        });
    }

    public function testUnpriviledgedUserCannotTranslate()
    {

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $dataObject->setCanEdit(false);
            $this->assertTrue((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should be default locale');
            $this->assertFalse($dataObject->canTranslate(), 'We\'re only allowed to translate in default locale');
        });
    }

}
