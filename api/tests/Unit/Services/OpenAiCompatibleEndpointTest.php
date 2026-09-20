<?php

namespace Tests\Unit\Services;

use App\Modules\Ledger\Services\OpenAiCompatibleEndpoint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ai-import')]
#[CoversClass(OpenAiCompatibleEndpoint::class)]
class OpenAiCompatibleEndpointTest extends TestCase
{
    #[DataProvider('completionsUrls')]
    public function testCompletionsUrlIsNormalized(string $input, string $expected): void
    {
        $this->assertSame($expected, OpenAiCompatibleEndpoint::completionsUrl($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function completionsUrls(): array
    {
        return [
            'origin only' => ['https://api.deepseek.com', 'https://api.deepseek.com/v1/chat/completions'],
            'trailing slash origin' => ['https://api.deepseek.com/', 'https://api.deepseek.com/v1/chat/completions'],
            'v1 path' => ['https://api.groq.com/openai/v1', 'https://api.groq.com/openai/v1/chat/completions'],
            'localhost ollama' => ['http://127.0.0.1:11434/v1', 'http://127.0.0.1:11434/v1/chat/completions'],
            'already complete' => ['http://localhost:8080/v1/chat/completions', 'http://localhost:8080/v1/chat/completions'],
            'ipv6' => ['http://[::1]:8080/v1', 'http://[::1]:8080/v1/chat/completions'],
        ];
    }

    public function testIdentityIgnoresTrailingSlash(): void
    {
        $this->assertSame(
            OpenAiCompatibleEndpoint::identity('http://127.0.0.1:11434/v1'),
            OpenAiCompatibleEndpoint::identity('http://127.0.0.1:11434/v1/'),
        );
    }

    public function testUserinfoIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not include credentials');

        OpenAiCompatibleEndpoint::assertValidBaseUrl('https://user:secret@api.example.com/v1');
    }

    public function testNonHttpSchemesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must use http or https');

        OpenAiCompatibleEndpoint::assertValidBaseUrl('ftp://127.0.0.1/v1');
    }
}
