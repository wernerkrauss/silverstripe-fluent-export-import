<?php

namespace Netwerkstatt\FluentExIm\Translator;

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

    /**
     * @todo make system message to ChatGPT configurable
     *
     * @param string $text
     * @param string $targetLocale
     * @return string
     */
    public function translate(string $text, string $targetLocale): string
    {
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini', // Modell von OpenAI
            'messages' => [
                [
                    'role' => 'system',
                    'content' => sprintf('You are a professional translator. Translate the following text to %s language.
                    Please keep the json format intact.', $targetLocale)
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
