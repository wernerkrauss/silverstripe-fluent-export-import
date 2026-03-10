<?php

namespace Netwerkstatt\FluentExIm\Translator;

use Exception;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use \DeepL\DeepLClient;

class DeepLTranslator implements Translatable
{
    use Extensible;
    use Configurable;
    use Injectable;
    use APITranslator;

    private DeepLClient $client;

    private static $sourceLocales = null;
    private static $targetLocales = null;
    private static $glossaries = [];

    public function __construct(string|null $apiKey = null)
    {
        if ($apiKey === null) {
            $apiKey = $this->getAPIKey("DEEPL_API_KEY");
        }

        $this->client = new DeepLClient($apiKey);

        if (self::$sourceLocales === null) {
            self::$sourceLocales = static::config()->get("source_locales");
        }

        if (self::$targetLocales === null) {
            self::$targetLocales = static::config()->get("target_locales");
        }

        if (self::$glossaries === []) {
            self::$glossaries = static::config()->get("glossaries");
        }
    }

    /**
     * Maximum payload size per DeepL API request in bytes.
     * DeepL rejects requests larger than 75kB; we use 70kB to stay safely below.
     */
    private static int $max_chunk_bytes = 70000;

    #[\Override]
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        try {
            $usage = $this->client->getUsage();
            if ($usage->anyLimitReached()) {
                throw new RuntimeException('Translation failed: DeepL API character limit reached');
            }
            $json = json_decode($text, true);
            foreach ($json as $key => $value) {
                $options = [
                    'tag_handling' => 'html',
                    'tag_handling_version' => 'v2',
                ];
                if (self::$glossaries && array_key_exists(self::$targetLocales[$targetLocale], self::$glossaries)) {
                    $options['glossary'] = self::$glossaries[self::$targetLocales[$targetLocale]];
                }
                if (is_string($value)) {
                    $json[$key] = $this->translateString(
                        $value,
                        self::$sourceLocales[$sourceLocale],
                        self::$targetLocales[$targetLocale],
                        $options,
                    );
                }
            }
            return json_encode($json);
        }
        catch (Exception $exception) {
            throw new RuntimeException('Translation failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Translates a single string, splitting it into chunks if it exceeds the
     * maximum payload size to avoid 413 Payload Too Large errors from the DeepL API.
     */
    private function translateString(string $value, string $sourceLang, string $targetLang, array $options): string
    {
        if (strlen($value) <= self::$max_chunk_bytes) {
            return $this->client->translateText($value, $sourceLang, $targetLang, $options)->text;
        }

        $chunks = $this->splitHtmlIntoChunks($value, self::$max_chunk_bytes);
        $translated = '';
        foreach ($chunks as $chunk) {
            $translated .= $this->client->translateText($chunk, $sourceLang, $targetLang, $options)->text;
        }

        return $translated;
    }

    /**
     * Splits an HTML string into chunks no larger than $maxBytes, always
     * breaking after a closing block-level tag so that no HTML is split mid-tag.
     *
     * @return string[]
     */
    private function splitHtmlIntoChunks(string $html, int $maxBytes): array
    {
        $blockTags = 'p|div|li|ul|ol|h[1-6]|table|tr|td|th|thead|tbody|tfoot|'
            . 'section|article|header|footer|blockquote|pre|figure|figcaption';

        preg_match_all(
            '/(<\/(?:' . $blockTags . ')>)/i',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $chunks = [];
        $currentChunk = '';
        $lastPos = 0;

        foreach ($matches[0] as $match) {
            $endPos  = $match[1] + strlen($match[0]);
            $segment = substr($html, $lastPos, $endPos - $lastPos);

            if ($currentChunk !== '' && strlen($currentChunk) + strlen($segment) > $maxBytes) {
                $chunks[]     = $currentChunk;
                $currentChunk = $segment;
            } else {
                $currentChunk .= $segment;
            }

            $lastPos = $endPos;
        }

        // Remaining text after the last block tag (or the entire string if no block tags found)
        if ($lastPos < strlen($html)) {
            $remaining = substr($html, $lastPos);
            if ($currentChunk !== '' && strlen($currentChunk) + strlen($remaining) > $maxBytes) {
                $chunks[]     = $currentChunk;
                $currentChunk = $remaining;
            } else {
                $currentChunk .= $remaining;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks ?: [$html];
    }
}
