<?php

namespace Netwerkstatt\FluentExIm\Translator;

use SilverStripe\ORM\DataObject;

class AITranslationStatus
{
    public const NOTHINGTOTRANSLATE = 'Nothing to translate';
    public const TRANSLATED = 'Translated';
    public const PUBLISHED = 'Translated and published';
    public const NOTAUTOTRANSLATED = 'Not auto translated';
    public const ALREADYTRANSLATED = 'Already translated';
    public const ERROR = 'Error';

    private DataObject $object;
    private array $locales_translated_to = [];
    private string $status;
    private string $message;
    private string $source;
    private string $aiResponse;
    private array|string $data;

    public function __construct(
        DataObject $object,
        string $status = '',
        string $message = '',
        string $source = '',
        string $aiResponse = '',
        array $data = []
    ) {
        if ($status === '') {
            $status = self::ERROR;
        }
        $this->object = $object;
        $this->status = $status;
        $this->message = $message;
        $this->source = $source;
        $this->aiResponse = $aiResponse;
        $this->data = $data;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getLocalesTranslatedTo(): array
    {
        return $this->locales_translated_to;
    }

    public function addLocale(string $locale, string $status): self
    {
        $this->locales_translated_to[$locale] = $status;
        return $this;
    }

    public function setSource(string $source): AITranslationStatus
    {
        $this->source = $source;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setAiResponse(string $aiResponse): AITranslationStatus
    {
        $this->aiResponse = $aiResponse;
        return $this;
    }

    public function getAiResponse(): string
    {
        return $this->aiResponse;
    }

    public function setData(array|string $data): AITranslationStatus
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array|string
    {
        return $this->data;
    }

    public function getObject(): DataObject
    {
        return $this->object;
    }

    public static function getLogLevel(string $status): string
    {
        return match ($status) {
            self::ALREADYTRANSLATED, self::NOTAUTOTRANSLATED, self::NOTHINGTOTRANSLATE => 'warning',
            self::ERROR => 'error',
            default => 'info',
        };
    }
}