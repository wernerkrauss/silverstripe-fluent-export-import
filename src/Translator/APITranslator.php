<?php

namespace Netwerkstatt\FluentExIm\Translator;

use RuntimeException;
use SilverStripe\Core\Environment;

trait APITranslator
{
    public static function getAPIKey(string $k) {
        $apiKey = Environment::getEnv($k);
        if (!$apiKey) {
            throw new RuntimeException('No API Key found');
        }

        return $apiKey;
    }
}