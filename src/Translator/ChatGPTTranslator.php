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

    public function getModels()
    {
        return $this->client->models()->list();
    }

    /**
     * @param string $text
     * @param string $targetLocale
     * @return string
     */
    public function translate(string $text, string $targetLocale): string
    {
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

        return $response->choices[0]->message->content; // Übersetzter Text
    }

    private function getGPTCommand(string $targetLocale): string
    {
        $command = self::config()->get('gpt_command');

        $this->extend('updateGPTCommand', $command);

        return sprintf($command, $targetLocale);
    }
}
