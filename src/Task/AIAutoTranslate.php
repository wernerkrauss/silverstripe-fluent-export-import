<?php

namespace Netwerkstatt\FluentExIm\Task;

use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AIAutoTranslate extends BuildTask
{
    private static $segment = 'fluent-ai-autotranslate';


    /**
     * @config
     */
    private static $is_enabled = true;

    protected $enabled = true;

    /**
     * @config
     */
    protected $title = 'AI Auto Translate';

    /**
     * @config
     */
    protected $description = 'Translate all translatable fields using AI; requires ChatGPT API key; Needs AutoTranslate extension';


    /**
     * @inheritDoc
     */
    public function run($request)
    {
        $defaultLocale = Locale::getDefault()->Locale;
        $currentLocale = Locale::getCurrentLocale()->Locale;
        if ($currentLocale !== $defaultLocale && $request->getVar('locale')) {
            $defaultLocale = $request->getVar('locale');
            FluentState::singleton()->setLocale($defaultLocale);
            $currentLocale = Locale::getCurrentLocale()->Locale;
        }

        if ($currentLocale !== $defaultLocale) {
            throw new \RuntimeException('Please run this task in default locale');
        }

        $fluentClasses = FluentHelper::getFluentClasses();
        foreach ($fluentClasses as $fluentClassName) {
            $fluentClass = singleton($fluentClassName);
            if (!$fluentClass->hasExtension(AutoTranslate::class)) {
                continue;
            }
            $translatableItems = FluentState::singleton()
                ->setLocale($defaultLocale)
                ->withState(static function (FluentState $state) use ($fluentClass) {
                    return $fluentClass::get();
                });
            foreach ($translatableItems as $translatableItem) {
                $translatableItem->autoTranslate();
            }
        }
    }
}
