<?php

namespace App\Modules\Ledger\Services;

use App\Models\AiProviderSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class ByokStatementParser
{
    /**
     * @return array{
     *     bank_accounts: list<array<string, mixed>>,
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function parse(AiProviderSetting $setting, string $contents, string $mimeType): array
    {
        $prompt = $this->prompt($contents, $mimeType);

        $content = match ($setting->provider) {
            'openai' => $this->parseWithOpenAi($setting, $prompt),
            'anthropic' => $this->parseWithAnthropic($setting, $prompt),
            default => throw new RuntimeException('The configured AI provider is not supported.'),
        };

        $content = preg_replace('/\A```(?:json)?\s*|\s*```\z/i', '', trim($content)) ?? $content;

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The AI provider returned invalid statement JSON.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The AI provider did not return a statement object.');
        }

        $accounts = $decoded['bank_accounts'] ?? [];
        $transactions = $decoded['transactions'] ?? [];

        if (! is_array($accounts) || ! is_array($transactions)) {
            throw new RuntimeException('The AI provider returned an invalid statement structure.');
        }

        return [
            'bank_accounts' => array_values(array_filter($accounts, 'is_array')),
            'transactions' => array_values(array_filter($transactions, 'is_array')),
        ];
    }

    private function parseWithOpenAi(AiProviderSetting $setting, string $prompt): string
    {
        $response = $this->request()
            ->withToken($setting->api_key)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $setting->model ?: 'gpt-4.1-mini',
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract bank statements into strict JSON. Never invent transactions.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI returned an empty statement response.');
        }

        return $content;
    }

    private function parseWithAnthropic(AiProviderSetting $setting, string $prompt): string
    {
        $response = $this->request()
            ->withHeaders([
                'x-api-key' => $setting->api_key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $setting->model ?: 'claude-haiku-4-5',
                'max_tokens' => 8192,
                'temperature' => 0,
                'system' => 'You extract bank statements into strict JSON. Never invent transactions.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->throw();

        $blocks = $response->json('content');

        if (! is_array($blocks)) {
            throw new RuntimeException('Anthropic returned an empty statement response.');
        }

        $content = collect($blocks)
            ->where('type', 'text')
            ->pluck('text')
            ->filter(static fn (mixed $text): bool => is_string($text))
            ->implode("\n");

        if ($content === '') {
            throw new RuntimeException('Anthropic returned an empty statement response.');
        }

        return $content;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(120)
            ->retry(2, 500, throw: false);
    }

    private function prompt(string $contents, string $mimeType): string
    {
        return <<<'PROMPT'
Extract every transaction that is actually present in the bank statement below.
Return one JSON object and no markdown using exactly this shape:
{
  "bank_accounts": [
    {
      "external_id": "stable account identifier from the statement, or a deterministic label",
      "name": "bank account display name",
      "last_four": "last four digits when available, otherwise null",
      "ownership": "personal or joint"
    }
  ],
  "transactions": [
    {
      "external_id": "bank transaction id when available, otherwise null",
      "bank_account_external_id": "matching bank account external_id",
      "date": "YYYY-MM-DD",
      "description": "normalized merchant or transaction description",
      "raw_description": "verbatim statement description",
      "amount_cents": 1234,
      "transaction_type": "expense or income",
      "confidence": 0.95,
      "rationale": "short explanation"
    }
  ]
}
Amounts must be positive integer minor currency units. Use expense for debits and income for credits.
Keep low-confidence rows instead of omitting them when their date and amount are legible.
PROMPT
            ."\n\nMIME type: {$mimeType}\n\nSTATEMENT:\n"
            .$contents;
    }
}
