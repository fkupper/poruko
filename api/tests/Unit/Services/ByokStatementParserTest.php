<?php

namespace Tests\Unit\Services;

use App\Models\AiProviderSetting;
use App\Modules\Ledger\Services\ByokStatementParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
