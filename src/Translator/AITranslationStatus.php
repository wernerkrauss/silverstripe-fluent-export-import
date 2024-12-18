<?php

namespace Netwerkstatt\FluentExIm\Translator;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;

class AITranslationStatus extends ViewableData
{
    public const STATUS_NOTHINGTOTRANSLATE = 'Nothing to translate';

    public const STATUS_TRANSLATED = 'Translated';

    public const STATUS_PUBLISHED = 'Translated and published';

    public const STATUS_NOTAUTOTRANSLATED = 'Not auto translated';

    public const STATUS_ALREADYTRANSLATED = 'Already translated';

    public const STATUS_ERROR = 'Error';

    public const ERRORMSG_NOTDEFAULTLOCALE = 'Item not in default locale';

    public const ERRORMSG_NOTHINGFOUND = 'No translatable fields found';

    private readonly DataObject $object;

    private array $locales_translated_to = [];

    private string $status;

    public function __construct(
        DataObject $object,
        string $status = '',
        private string $message = '',
        private string $source = '',
        private string $aiResponse = '',
        private array|string $data = []
    ) {
        if ($status === '') {
            $status = self::STATUS_ERROR;
        }

        $this->failover = $object;
        $this->object = $object;
        $this->status = $status;
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

    public function getLocalesTranslatedToForTemplate()
    {
        $data = ArrayList::create();
        foreach ($this->getLocalesTranslatedTo() as $locale => $status) {
            $data->push(ArrayData::create([
                'Locale' => $locale,
                'Status' => $status,
            ]));
        }

        return $data;
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
            self::STATUS_ALREADYTRANSLATED, self::STATUS_NOTAUTOTRANSLATED, self::STATUS_NOTHINGTOTRANSLATE => 'warning',
            self::STATUS_ERROR => 'error',
            default => 'info',
        };
    }
}
