<?php

namespace Tests\Unit\Services;

use App\Enums\AiProvider;
use App\Models\AiProviderSetting;
use App\Modules\Ledger\Services\ByokStatementParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('ai-import')]
#[CoversClass(ByokStatementParser::class)]
class ByokStatementParserTest extends TestCase
{
    use RefreshDatabase;

    public function testAnthropicResponseIsNormalized(): void
    {
        $setting = AiProviderSetting::factory()->create([
            'provider' => 'anthropic',
            'model' => 'claude-test',
        ]);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => <<<'JSON'
```json
{"bank_accounts":[{"external_id":"a","name":"Checking","ownership":"personal"}],"transactions":[]}
```
JSON,
                ]],
            ]),
        ]);

        $result = $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');

        $this->assertSame('Checking', $result['bank_accounts'][0]['name']);
        $this->assertSame([], $result['transactions']);
        Http::assertSent(
            fn ($request): bool => $request->hasHeader('x-api-key', $setting->api_key)
                && $request['model'] === 'claude-test',
        );
    }

    public function testInvalidProviderJsonIsRejected(): void
    {
        $setting = AiProviderSetting::factory()->create(['provider' => 'openai']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'not-json']]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid statement JSON');

        $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');
    }

    public function testOpenAiCompatibleRequestUsesConfiguredHostWithoutJsonMode(): void
    {
        $setting = AiProviderSetting::factory()->openaiCompatible()->create();
        Http::fake([
            'http://127.0.0.1:11434/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'bank_accounts' => [['external_id' => 'a', 'name' => 'Local Bank', 'ownership' => 'personal']],
                    'transactions' => [],
                ], JSON_THROW_ON_ERROR)]]],
            ]),
            '*' => Http::response('unexpected host', 500),
        ]);

        $result = $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');

        $this->assertSame('Local Bank', $result['bank_accounts'][0]['name']);
        Http::assertSent(function ($request) use ($setting): bool {
            return $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer ' . $setting->api_key)
                && $request['model'] === 'llama3.1'
                && !isset($request['response_format']);
        });
        Http::assertNotSent(
            fn ($request): bool => str_contains($request->url(), 'api.openai.com')
                || str_contains($request->url(), 'api.anthropic.com'),
        );
    }

    public function testOfficialOpenAiIgnoresStoredBaseUrl(): void
    {
        $setting = AiProviderSetting::factory()->create([
            'provider' => AiProvider::OpenAi,
            'base_url' => 'http://127.0.0.1:9999/v1',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"bank_accounts":[],"transactions":[]}']]],
            ]),
            '*' => Http::response('unexpected host', 500),
        ]);

        $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');

        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['response_format']['type'] === 'json_object',
        );
        Http::assertNotSent(
            fn ($request): bool => str_contains($request->url(), '127.0.0.1'),
        );
    }

    public function testCompatibleHttpErrorsAreMapped(): void
    {
        $setting = AiProviderSetting::factory()->openaiCompatible()->create();
        Http::fake([
            'http://127.0.0.1:11434/*' => Http::response(['error' => 'nope'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');
    }

    public function testCompatibleConnectionErrorsAreMapped(): void
    {
        $setting = AiProviderSetting::factory()->openaiCompatible()->create();
        Http::fake(static function (): never {
            throw new ConnectionException('Failed to connect');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not reach the configured AI endpoint');

        $this->app->make(ByokStatementParser::class)
            ->parse($setting, 'statement contents', 'text/csv');
    }

    public function testCompatibleUserinfoUrlsAreRejectedBeforeRequest(): void
    {
        $setting = AiProviderSetting::factory()->openaiCompatible('https://stolen:key@evil.example/v1')->create();
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not include credentials');

        try {
            $this->app->make(ByokStatementParser::class)
                ->parse($setting, 'statement contents', 'text/csv');
        } finally {
            Http::assertNothingSent();
        }
    }
}
