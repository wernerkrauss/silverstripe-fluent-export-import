<?php

namespace Netwerkstatt\FluentExIm\Task;

use Exception;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Netwerkstatt\FluentExIm\Extension\AutoTranslate;
use Netwerkstatt\FluentExIm\Helper\FluentHelper;
use Netwerkstatt\FluentExIm\Translator\AITranslationStatus;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AIAutoTranslate extends BuildTask
{
    protected static string $commandName = 'FluentAIAutoTranslate';

    /**
     * @config
     */
    private static $segment = 'fluent-ai-autotranslate';
    /**
     * @config
     */
    private static $is_enabled = true;

    /**
     * @config
     */
    protected string $title = 'AI Auto Translate';

    /**
     * @config
     */
    protected static string $description = 'Translate all translatable fields using AI; requires ChatGPT API key; Needs AutoTranslate extension';

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
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $defaultLocale = Locale::getDefault()->Locale;
        $currentLocale = Locale::getCurrentLocale()?->Locale;

        if ($currentLocale !== $defaultLocale && $input->hasOption('locale_from')) {
            $defaultLocale = $input->getOption('locale_from');
            FluentState::singleton()->setLocale($defaultLocale);
            $currentLocale = Locale::getCurrentLocale()->Locale;
        }

        if ($currentLocale !== $defaultLocale) {
            throw new RuntimeException('Please run this task in default locale or set --locale-from to a valid locale');
        }

        if ($input->getOption('do_publish') === null) {
            throw new InvalidArgumentException('Please provide do_publish parameter. 1 will publish all translated objects, 0 will only write to stage');
        }

        $doPublish = (bool)$input->getOption('do_publish');
        $forceTranslation = (bool)$input->getOption('force_translation');

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

            $output->startList(); //some space between classes
            $output->writeListItem('<options=bold,underscore>' . $fluentClass->singular_name() . '</>');
            $translatableItems = FluentState::singleton()
                ->setLocale($defaultLocale)
                ->withState(static fn(FluentState $state) => DataObject::get($fluentClassName));
            foreach ($translatableItems as $translatableItem) {
                $translatableItem = $translatableItem->fixLastTranslationForDefaultLocale();
                $status = $translatableItem->autoTranslate($doPublish, $forceTranslation);
                $this->outputStatus($output, $status);
            }
            $output->stopList();
        }
        return Command::SUCCESS;
    }

    private function outputStatus(PolyOutput $output, AITranslationStatus $status)
    {
        $msg = $status->getObject()->ClassName . ': ' . $status->getObject()->getTitle() . ' (' . $status->getObject()->ID . '): ';
        $msg = $status->getMessage() !== '' && $status->getMessage() !== '0' ? $msg . ' - ' . $status->getMessage() : $msg;

        $msg = match (AITranslationStatus::getLogLevel($status)) {
            'warning' => '<comment>' . $msg . '</comment>',
            'error' => '<error>' . $msg . '</error>',
            default => '<info>' . $msg . '</info>',
        };

        $output->startList();
        $output->writeListItem($msg);
        $localesTranslatedTo = $status->getLocalesTranslatedTo();
        if ($localesTranslatedTo !== []) {
            $output->startList();
            foreach ($localesTranslatedTo as $locale => $localeStatus) {
                $status = AITranslationStatus::getLogLevel($localeStatus);
                $msg = $locale . ': ' . $localeStatus;
                $msg = match ($status) {
                    'warning' => '<comment>' . $msg . '</comment>',
                    'error' => '<error>' . $msg . '</error>',
                    default => '<info>' . $msg . '</info>',
                };
                $output->writeListItem($msg);
//                $this->log(AITranslationStatus::getLogLevel($localeStatus), ' * ' . $locale . ': ' . $localeStatus);
            }
            $output->stopList();
        }
        $output->stopList();
    }

    /**
     * Taken from \SilverStripe\Dev\Tasks\MigrateFileTask
     * @throws Exception
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

    public function getOptions(): array
    {
        return [
            new InputOption(
                'locale_from',
                'l',
                InputOption::VALUE_REQUIRED,
                'set the locale to translate from; use the default locale if not set.',
                Locale::getDefault()->Locale,
                Locale::get()->column('Locale')
            ),
            new InputOption(
                'do_publish',
                'p',
                InputOption::VALUE_REQUIRED,
                'publish records. Must be set to 1 for this task to run',
                null, //must be set
                [0, 1]
            ),
            new InputOption(
                'force_translation',
                null,
                InputOption::VALUE_NONE,
                'Should all texts be translated again, even if they\'re already translated?'
            )
        ];
    }
}
