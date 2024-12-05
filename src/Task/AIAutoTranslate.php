<?php

namespace Netwerkstatt\FluentExIm\Task;

use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use SilverStripe\Dev\BuildTask;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AIAutoTranslate extends BuildTask
{
    /**
     * @config
     */
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

        $doPublish = $request->getVar('do_publish');
        if ($doPublish === null) {
            throw new \InvalidArgumentException('Please provide do_publish parameter. 1 will publish all translated objects, 0 will only write to stage');
        }

        $fluentClasses = FluentHelper::getFluentClasses();
        foreach ($fluentClasses as $fluentClassName) {
            $fluentClass = singleton($fluentClassName);
            if (!$fluentClass->hasExtension(AutoTranslate::class)) {
                continue;
            }

            $translatableItems = FluentState::singleton()
                ->setLocale($defaultLocale)
                ->withState(static fn(FluentState $state) => $fluentClass::get());
            foreach ($translatableItems as $translatableItem) {
                $translatableItem = $translatableItem->fixLastTranslationForDefaultLocale();
                $translatableItem->autoTranslate($doPublish);
            }
        }
    }
}
