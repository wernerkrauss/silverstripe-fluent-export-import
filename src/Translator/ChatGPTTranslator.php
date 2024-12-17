<?php

namespace Netwerkstatt\FluentExIm\Translator;

use OpenAI\Client;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class ChatGPTTranslator implements Translatable
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * @config
     */
    private static string $gpt_model = 'gpt-4o-mini';

    /**
     * @config
     */
    private static string $gpt_command = 'You are a professional translator. Translate the following text to %s language. Please keep the json format intact.';

    private Client $client;

    public function __construct(string $apiKey)
    {
        $this->client = \OpenAI::client($apiKey);
    }

    /**
     * Retrieves available models from the OpenAI API.
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getModels(): array
    {
        try {
            return $this->client->models()->list()->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException('Error retrieving models: ' . $e->getMessage());
        }
    }

    /**
     * Translates the given text to the target language.
     *
     * @param string $text The text to translate.
     * @param string $targetLocale The target locale/language code.
     * @return string Translated text.
     * @throws \RuntimeException
     */
    public function translate(string $text, string $targetLocale): string
    {
        try {
            $response = $this->client->chat()->create([
                'model' => self::config()->get('gpt_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getGPTCommand($targetLocale)
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ]
            ]);

            // Sicherstellen, dass die Antwort korrekt ist
            if (isset($response->choices[0]->message->content)) {
                return $response->choices[0]->message->content;
            }

            throw new \RuntimeException('Invalid response structure from API');
        } catch (\Exception $e) {
            throw new \RuntimeException('Translation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generates the translation command for the GPT model.
     *
     * @param string $targetLocale The target locale/language code.
     * @return string The generated command for the GPT model.
     */
    private function getGPTCommand(string $targetLocale): string
    {
        $command = self::config()->get('gpt_command');

        // Erweiterung der Befehlslogik durch andere Klassen
        $this->extend('updateGPTCommand', $command);

        return sprintf($command, $targetLocale);
    }
}
