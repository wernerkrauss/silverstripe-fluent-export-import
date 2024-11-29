<?php

namespace Netwerkstatt\FluentExIm\Translator;

use Netwerkstatt\FluentExIm\Translator\Translatable;


class ChatGPTTranslator implements Translatable
{
    private $client;

    public function __construct(string $apiKey)
    {
        $this->client = \OpenAI::client($apiKey); // OpenAI-Client initialisieren
    }

    public function getModels()
    {
        return $this->client->models()->list();
    }

    public function translate(string $text, string $targetLocale): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini', // Modell von OpenAI
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a professional translator. Translate the following text to {$targetLocale} language."
                ],
                [
                    'role' => 'user',
                    'content' => $text
                ]
            ]
        ]);

        return $response->choices[0]->message->content; // Übersetzter Text
    }
}
