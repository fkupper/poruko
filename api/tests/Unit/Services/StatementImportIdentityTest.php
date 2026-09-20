<?php

namespace Tests\Unit\Services;

use App\Models\BankAccountMapping;
use App\Modules\Ledger\Services\StatementImportIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ai-import')]
#[CoversClass(StatementImportIdentity::class)]
class StatementImportIdentityTest extends TestCase
{
    #[DataProvider('accountIdentityDataProvider')]
    public function testAccountNumbersSurviveNameDrift(array $first, array $second): void
    {
        $left = StatementImportIdentity::fromParsedAccount($first);
        $right = StatementImportIdentity::fromParsedAccount($second);

        $this->assertSame($left->fingerprint, $right->fingerprint);
        $this->assertSame('248016970', $left->digits);
        $this->assertSame('6970', $left->lastFour);
        $this->assertTrue($left->matchesMapping(new BankAccountMapping([
            'external_account_fingerprint' => 'unrelated',
            'external_account_name' => $second['name'],
            'masked_identifier' => '•••• 6970',
        ])));
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    public static function accountIdentityDataProvider(): array
    {
        return [
            'Account prefix vs bank name' => [
                [
                    'external_id' => '248016970',
                    'name' => 'Account 248016970',
                    'last_four' => '6970',
                ],
                [
                    'external_id' => '248016970',
                    'name' => 'ABN AMRO Bank N.V. 248016970',
                    'last_four' => '6970',
                ],
            ],
        ];
    }

    public function testLastFourStillMatchesAFullAccountNumber(): void
    {
        $full = StatementImportIdentity::fromParsedAccount([
            'external_id' => '248016970',
            'name' => 'Account 248016970',
            'last_four' => '6970',
        ]);
        $partial = StatementImportIdentity::fromParsedAccount([
            'external_id' => '6970',
            'name' => '6970',
            'last_four' => '6970',
        ]);

        $this->assertTrue($full->matchesMapping(new BankAccountMapping([
            'external_account_fingerprint' => $partial->fingerprint,
            'external_account_name' => '6970',
            'masked_identifier' => '•••• 6970',
        ])));
        $this->assertTrue(StatementImportIdentity::digitsMatch($full->digits, $partial->digits));
    }

    public function testTransactionFingerprintIgnoresNormalizedMerchantLabels(): void
    {
        $account = StatementImportIdentity::fromParsedAccount([
            'external_id' => '248016970',
            'name' => 'Account 248016970',
            'last_four' => '6970',
        ]);
        $raw = '/TRTP/SEPA Incasso algemeen doorlopend/CSID/NL39ZZ';

        $this->assertSame(
            StatementImportIdentity::transactionFingerprint($account->fingerprint, '2026-08-20', 400, $raw),
            StatementImportIdentity::transactionFingerprint(
                StatementImportIdentity::fromParsedAccount([
                    'external_id' => '248016970',
                    'name' => 'ABN AMRO Bank N.V. 248016970',
                    'last_four' => '6970',
                ])->fingerprint,
                '2026-08-20',
                400,
                $raw,
            ),
        );
        $this->assertSame(
            StatementImportIdentity::contentKey('2026-08-20', 400, $raw),
            StatementImportIdentity::contentKey('2026-08-20', 400, mb_strtoupper($raw)),
        );
    }

    public function testShortDigitSuffixesDoNotMatch(): void
    {
        $this->assertFalse(StatementImportIdentity::digitsMatch('248016970', '70'));
        $this->assertTrue(StatementImportIdentity::digitsMatch('248016970', '6970'));
    }
}
