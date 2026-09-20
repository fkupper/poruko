<?php

namespace App\Modules\Ledger\Services;

use App\Enums\AiProvider;
use App\Models\AiProviderSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

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
            AiProvider::OpenAi => $this->parseWithOpenAi($setting, $prompt),
            AiProvider::Anthropic => $this->parseWithAnthropic($setting, $prompt),
            AiProvider::OpenAiCompatible => $this->parseWithOpenAiCompatible($setting, $prompt),
        };

        $content = preg_replace('/\A```(?:json)?\s*|\s*```\z/i', '', trim($content)) ?? $content;

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The AI provider returned invalid statement JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('The AI provider did not return a statement object.');
        }

        $accounts = $decoded['bank_accounts'] ?? [];
        $transactions = $decoded['transactions'] ?? [];

        if (!is_array($accounts) || !is_array($transactions)) {
            throw new RuntimeException('The AI provider returned an invalid statement structure.');
        }

        return [
            'bank_accounts' => array_values(array_filter($accounts, 'is_array')),
            'transactions' => array_values(array_filter($transactions, 'is_array')),
        ];
    }

    private function parseWithOpenAi(AiProviderSetting $setting, string $prompt): string
    {
        return $this->parseOpenAiProtocol(
            $setting,
            $prompt,
            'https://api.openai.com/v1/chat/completions',
            $setting->model ?: 'gpt-4.1-mini',
            forceJsonObject: true,
            emptyMessage: 'OpenAI returned an empty statement response.',
        );
    }

    private function parseWithOpenAiCompatible(AiProviderSetting $setting, string $prompt): string
    {
        if (!is_string($setting->base_url) || $setting->base_url === '') {
            throw new RuntimeException('Configure a base URL for the OpenAI-compatible endpoint.');
        }

        $model = $setting->model;

        if (!is_string($model) || $model === '') {
            throw new RuntimeException('Configure a model for the OpenAI-compatible endpoint.');
        }

        try {
            $url = OpenAiCompatibleEndpoint::completionsUrl($setting->base_url);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        return $this->parseOpenAiProtocol(
            $setting,
            $prompt,
            $url,
            $model,
            forceJsonObject: false,
            emptyMessage: 'The OpenAI-compatible provider returned an empty statement response.',
        );
    }

    private function parseOpenAiProtocol(
        AiProviderSetting $setting,
        string $prompt,
        string $url,
        string $model,
        bool $forceJsonObject,
        string $emptyMessage,
    ): string {
        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You extract bank statements into strict JSON. Never invent transactions.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($forceJsonObject) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->send(
            fn (): Response => $this->request()
                ->withToken($setting->api_key)
                ->post($url, $payload),
        );

        $content = $response->json('choices.0.message.content');

        if (!is_string($content) || $content === '') {
            throw new RuntimeException($emptyMessage);
        }

        return $content;
    }

    private function parseWithAnthropic(AiProviderSetting $setting, string $prompt): string
    {
        $response = $this->send(
            fn (): Response => $this->request()
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
                ]),
        );

        $blocks = $response->json('content');

        if (!is_array($blocks)) {
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
            ->timeout(StatementImportLimits::HTTP_TIMEOUT_SECONDS)
            ->connectTimeout(StatementImportLimits::HTTP_CONNECT_TIMEOUT_SECONDS)
            ->withOptions(['allow_redirects' => false])
            ->retry(2, 500, $this->shouldRetry(...), throw: false);
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $exception->response->serverError();
        }

        if (!$exception instanceof ConnectionException) {
            return false;
        }

        return !str_contains(mb_strtolower($exception->getMessage()), 'operation timed out');
    }

    /**
     * @param callable(): Response $send
     */
    private function send(callable $send): Response
    {
        try {
            return $send()->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not reach the configured AI endpoint. Check the base URL and that the server is running.',
                previous: $exception,
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw new RuntimeException(
                "The AI provider rejected the request (HTTP {$status}).",
                previous: $exception,
            );
        }
    }

    private function prompt(string $contents, string $mimeType): string
    {
        return <<<'PROMPT'
Extract every transaction that is actually present in the bank statement below.
Return one JSON object and no markdown using exactly this shape:
{
  "bank_accounts": [
    {
      "external_id": "account number digits only, stable across statements",
      "name": "bank or product name without an Account prefix",
      "last_four": "exactly four digits from the account number when available, otherwise null",
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
      "sharing_type": "individual or shared",
      "confidence": 0.95,
      "rationale": "short explanation"
    }
  ]
}
Amounts must be positive integer minor currency units. Use expense for debits and income for credits.
external_id for bank accounts must be the account number digits, never a display label such as Account 1234.
Reuse the same account number when the same account appears again, even if the bank name is written differently.
sharing_type is individual when the expense belongs to one person and shared when it should be split with the household.
Keep low-confidence rows instead of omitting them when their date and amount are legible.
PROMPT
            . "\n\nMIME type: {$mimeType}\n\nSTATEMENT:\n"
            . $contents;
    }
}
