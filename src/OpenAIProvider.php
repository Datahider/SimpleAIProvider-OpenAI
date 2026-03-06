<?php

namespace losthost\SimpleAI\Provider\OpenAI;

use losthost\SimpleAI\types\AIOptions;
use losthost\SimpleAI\types\Context;
use losthost\SimpleAI\types\ContextItem;
use losthost\SimpleAI\types\ProviderInterface;
use losthost\SimpleAI\types\Response;
use losthost\SimpleAI\types\Tools;

class OpenAIProvider implements ProviderInterface {

    public const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';

    public function request(Context $context, Tools $tools, AIOptions $options) : Response {

        $messages = [];

        foreach ($context->asArray() as $item) {
            if ($item->getRole() === ContextItem::ROLE_TOOL) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $item->getToolCall(),
                ];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $item->getToolCallId(),
                    'content' => $item->getContent()
                ];
            } elseif ($item->getRole() === ContextItem::ROLE_ASSISTANT && $item->getToolCall()) {
                $messages[] = [
                    'role' => 'assistant',
                    'tool_calls' => [$item->getToolCall()]
                ];
            } else {
                $messages[] = [
                    'role' => $item->getRole(),
                    'content' => $item->getContent()
                ];
            }
        }

        $tools_payload = [];
        foreach ($tools->asArray() as $tool) {
            $tools_payload[] = $tool->getDefinition();
        }

        $payload = [
            'model' => $options->model,
            'messages' => $messages,
            'temperature' => $options->temperature,
        ];

        if (preg_match('/^gpt-5/', $options->model)) {
            $payload['max_completion_tokens'] = $options->max_tokens;
        } else {
            $payload['max_tokens'] = $options->max_tokens;
        }

        if ($tools_payload) {
            $payload['tools'] = $tools_payload;
            $payload['tool_choice'] = 'auto';
        }

        $raw = $this->httpPostJson(self::OPENAI_API_URL, $payload, $options);

        $decoded = json_decode($raw, true);
        if (!$decoded) {
            throw new \RuntimeException("Invalid JSON from OpenAI: $raw");
        }

        if (!empty($decoded['error'])) {
            throw new \RuntimeException($decoded['error']['message'] ?? 'OpenAI API error');
        }

        return new Response($decoded);
    }

    protected function httpPostJson(string $url, array $payload, AIOptions $options) : string {

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $options->api_key,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $options->timeout,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $err");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("OpenAI HTTP $status: $response");
        }

        return $response;
    }
}
