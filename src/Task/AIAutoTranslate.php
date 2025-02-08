<?php

namespace Netwerkstatt\FluentExIm\Task;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use Netwerkstatt\FluentExIm\Translator\AITranslationStatus;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
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
     * @config
     * @var string[]
     */
    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class,
    ];

    /** @var Logger */
    public $logger;

    public function __construct()
    {
        parent::__construct();
        $this->addLogHandlers();
    }

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

        if ($request->getVar('do_publish') === null) {
            throw new \InvalidArgumentException('Please provide do_publish parameter. 1 will publish all translated objects, 0 will only write to stage');
        }

        $doPublish = (bool) $request->getVar('do_publish');
        $forceTranslation = (bool) $request->getVar('force_translation');



        $fluentClasses = FluentHelper::getFluentClasses();
        foreach ($fluentClasses as $fluentClassName) {
            $fluentClass = singleton($fluentClassName);
            if (!$fluentClass->hasExtension(AutoTranslate::class)) {
                continue;
            }

            if (get_parent_class($fluentClass) !== DataObject::class) {
                //fluent should only be applied to base classes
                continue;
            }

            echo PHP_EOL . '** ' . $fluentClass->singular_name() . ' **' . PHP_EOL;
            $translatableItems = FluentState::singleton()
                ->setLocale($defaultLocale)
                ->withState(static fn(FluentState $state) => DataObject::get($fluentClassName));
            foreach ($translatableItems as $translatableItem) {
                $translatableItem = $translatableItem->fixLastTranslationForDefaultLocale();
                $status = $translatableItem->autoTranslate($doPublish, $forceTranslation);
                $this->outputStatus($status);
            }
        }
    }

    private function outputStatus(AITranslationStatus $status)
    {
        $msg = $status->getObject()->ClassName . ': ' . $status->getObject()->getTitle() . ' (' . $status->getObject()->ID . '): ' . PHP_EOL;
        $msg = $status->getMessage() !== '' && $status->getMessage() !== '0' ? $msg . ' - ' . $status->getMessage() : $msg;
        $this->log($status->getStatus(), $msg);

        $localesTranslatedTo = $status->getLocalesTranslatedTo();
        if ($localesTranslatedTo !== []) {
            foreach ($localesTranslatedTo as $locale => $localeStatus) {
                $this->log(AITranslationStatus::getLogLevel($localeStatus), ' * ' . $locale . ': ' . $localeStatus);
            }
        }
    }

    /**
     * Taken from \SilverStripe\Dev\Tasks\MigrateFileTask
     * @throws \Exception
     */
    protected function addLogHandlers()
    {
        // Using a global service here so other systems can control and redirect log output,
        // for example when this task is run as part of a queuedjob
        $logger = Injector::inst()->get(LoggerInterface::class)->withName('log');

        $formatter = new LineFormatter();
        $formatter->ignoreEmptyContextAndExtra();

        $errorHandler = new StreamHandler('php://stderr', Level::Error);
        $errorHandler->setFormatter($formatter);

        $standardHandler = new StreamHandler('php://stdout');
        $standardHandler->setFormatter($formatter);

        // Avoid double logging of errors
        $standardFilterHandler = new FilterHandler(
            $standardHandler,
            Level::Debug,
            Level::Warning
        );

        $logger->pushHandler($standardFilterHandler);
        $logger->pushHandler($errorHandler);

        $this->logger = $logger;
    }

    /**
     * @param string $status
     * @param string $msg
     * @return void
     */
    private function log(string $status, string $msg): void
    {
        match ($status) {
            'warning' => $this->logger->warning($msg),
            'error' => $this->logger->error($msg),
            default => $this->logger->info($msg),
        };
    }
}
