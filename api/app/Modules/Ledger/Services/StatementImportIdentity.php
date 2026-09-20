<?php

namespace App\Modules\Ledger\Services;

use App\Models\BankAccountMapping;
use Illuminate\Support\Str;

final readonly class StatementImportIdentity
{
    private function __construct(
        public string $displayName,
        public ?string $lastFour,
        public string $digits,
        public string $fingerprint,
    ) {}

    /**
     * @param array<string, mixed> $parsedAccount
     */
    public static function fromParsedAccount(array $parsedAccount): self
    {
        $externalId = self::stringValue($parsedAccount, 'external_id');
        $name = self::stringValue($parsedAccount, 'name') ?? $externalId ?? 'Imported bank account';
        $lastFour = self::lastFour(self::stringValue($parsedAccount, 'last_four'));
        $digits = self::preferredDigits($externalId, $name, $lastFour);

        if ($lastFour === null) {
            $lastFour = self::lastFour($digits);
        }

        $fingerprintKey = strlen($digits) >= 4 ? $digits : self::normalize($name);

        return new self(
            displayName: $name,
            lastFour: $lastFour,
            digits: $digits,
            fingerprint: hash('sha256', $fingerprintKey),
        );
    }

    public function matchesMapping(BankAccountMapping $mapping): bool
    {
        if ($mapping->external_account_fingerprint === $this->fingerprint) {
            return true;
        }

        return self::digitsMatch(
            $this->digits,
            self::preferredDigits($mapping->external_account_name, $mapping->masked_identifier),
        );
    }

    public static function digitsMatch(string $left, string $right): bool
    {
        if ($left === '' || $right === '' || $left === $right) {
            return $left !== '' && $left === $right;
        }

        $shorter = strlen($left) <= strlen($right) ? $left : $right;
        $longer = $shorter === $left ? $right : $left;

        return strlen($shorter) >= 4 && str_ends_with($longer, $shorter);
    }

    public static function preferredDigits(?string ...$values): string
    {
        $best = '';

        foreach ($values as $value) {
            $digits = preg_replace('/\D+/', '', $value ?? '') ?? '';

            if (strlen($digits) > strlen($best)) {
                $best = $digits;
            }
        }

        return $best;
    }

    public static function transactionFingerprint(
        string $accountFingerprint,
        string $date,
        int $amount,
        string $rawDescription,
    ): string {
        return hash('sha256', $accountFingerprint.'|'.self::contentKey($date, $amount, $rawDescription));
    }

    public static function contentKey(string $date, int $amount, string $rawDescription): string
    {
        return $date.'|'.$amount.'|'.self::normalize($rawDescription);
    }

    public static function normalize(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    public static function lastFour(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value ?? '') ?? '';

        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function stringValue(array $value, string $key): ?string
    {
        $result = $value[$key] ?? null;

        if (!is_string($result) || trim($result) === '') {
            return null;
        }

        return trim($result);
    }
}
