<?php

namespace Netwerkstatt\FluentExIm\Tests\Translator;

use Netwerkstatt\FluentExIm\Translator\Translatable;
use SilverStripe\Dev\TestOnly;

class MockTranslator implements Translatable, TestOnly
{

    /**
     * @inheritDoc
     */
    public function translate(string $text, string $targetLocale): string
    {
        //assume we have a json string
        $translations = json_decode($text, true);
        foreach ($translations as $key => $value) {
            $translations[$key] = $value . ' (translated to ' . $targetLocale . ')';
        }

        return json_encode($translations);
    }
}
