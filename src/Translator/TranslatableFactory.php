<?php

namespace Netwerkstatt\FluentExIm\Translator;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

class TranslatableFactory {
    use Configurable;
    public static function getBackendName(): string {
        $backendName = Environment::getEnv("FLUENT_TRANS_BACKEND");

        if ($backendName === false || $backendName === null) {
            $backendName = static::config()->get("backend");
        }

        return $backendName;
    }

    public static function getBackend(): string {
        return self::config()->get("translators")[self::getBackendName()];
    }

    public static function getInstance(): Translatable {
        $backend = self::getBackend();

        if (!class_exists($backend)) {
            throw new \RuntimeException("Bad translation config.");
        }

        return Injector::inst()->get($backend);
    }
}
