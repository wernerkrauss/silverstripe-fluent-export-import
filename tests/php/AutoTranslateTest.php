<?php

namespace Netwerkstatt\FluentExIm\Tests;

use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use Netwerkstatt\FluentExIm\Tests\Stub\LocalisedDataObject;
use Netwerkstatt\FluentExIm\Tests\Translator\MockTranslator;
use Netwerkstatt\FluentExIm\Translator\AITranslationStatus;
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
        AutoTranslate::setTranslator(new MockTranslator());
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

    public function testHasDefaultLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertTrue($dataObject->hasDefaultLocale(), 'Locale en_US should be default locale');
            $newState
                ->setLocale('de_DE');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertFalse($dataObject->hasDefaultLocale(), 'Locale de_DE not should be default locale');
        });
    }

    public function testAutoTranslateFailures()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE');

            $dataObjectDE = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            /** @var AITranslationStatus $status */
            $status = $dataObjectDE->autoTranslate();
            $this->assertEquals(AITranslationStatus::ERRORMSG_NOTDEFAULTLOCALE,$status->getMessage(),  'AutoTranslate should fail if not in default locale');

            $newState
                ->setLocale('en_US');

            $emptyDataObject = LocalisedDataObject::create(['Locale' => 'en_US']);
            $emptyDataObject->write();
            $status = $emptyDataObject->autoTranslate();
            $this->assertEquals(AITranslationStatus::ERRORMSG_NOTHINGFOUND, $status->getMessage(), 'AutoTranslate should fail if no data found');
        });
    }

    public function testAutoTranslateMessagesPerLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate();
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_ALREADYTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return already translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate();
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }

    public function testAutoTranslateCanForceTranslation()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate(false, true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate(false, true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }

    public function testDoPublishIsIgnoredInUnversionedObjects()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate(true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_ALREADYTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return already translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate(true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }
}
