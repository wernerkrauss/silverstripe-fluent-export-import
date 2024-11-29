<?php

namespace Netwerkstatt\FluentExIm\Translator;

interface Translatable
{
    /**
     * Translates the given text to the target locale
     *
     * @param string $text
     * @param string $targetLocale
     * @return string
     */
    public function translate(string $text, string $targetLocale): string;
}
