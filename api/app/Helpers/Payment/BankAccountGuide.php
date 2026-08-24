<?php

namespace App\Helpers\Payment;

/**
 * Panduan rekening pembayaran — sumber terpusat dari Env::BCA_PAYMENT_ACCOUNTS.
 */
class BankAccountGuide
{
    /**
     * @return array<string,array{label:string,number:string,name:string}>
     */
    public static function accounts(): array
    {
        $raw = [];
        if (class_exists('Env') && defined('Env::BCA_PAYMENT_ACCOUNTS')) {
            $raw = \Env::BCA_PAYMENT_ACCOUNTS;
        }
        if (!is_array($raw) || empty($raw)) {
            $raw = self::defaultAccounts();
        }

        $out = [];
        foreach ($raw as $code => $row) {
            if (!is_array($row)) {
                continue;
            }
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $codeKey = strtoupper(trim((string) $code));
            $out[$codeKey] = [
                'label' => trim((string) ($row['label'] ?? $codeKey)),
                'number' => $number,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{label:string,number:string,name:string}|null
     */
    public static function bcaAccount(): ?array
    {
        $accounts = self::accounts();

        return $accounts['BCA'] ?? (reset($accounts) ?: null);
    }

    /**
     * Payload JSON kompatibel dengan laundry GET /Get/rekening.
     *
     * @return array{ok:bool,accounts:array<int,array{code:string,label:string,number:string,name:string}>,bca:?array,message:string,qris_url?:string,qris_image_url?:string}
     */
    public static function publicPayload(?string $qrisUrl = null, ?string $qrisImageUrl = null): array
    {
        $guide = self::accounts();
        $accounts = [];
        $lines = [];
        $ownerName = 'LUHUR GUNAWAN';

        if ($qrisUrl !== null && $qrisUrl !== '') {
            $lines[] = 'QRIS';
            $lines[] = $qrisUrl;
            $lines[] = '';
        }

        foreach ($guide as $code => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $ownerName = $name;
            }
            $accounts[] = [
                'code' => (string) $code,
                'label' => (string) $row['label'],
                'number' => (string) $row['number'],
                'name' => $name !== '' ? $name : $ownerName,
            ];
            $lines[] = (string) $row['label'];
            $lines[] = (string) $row['number'];
            $lines[] = '';
        }
        $lines[] = 'an. ' . $ownerName;

        $payload = [
            'ok' => true,
            'accounts' => $accounts,
            'bca' => self::bcaAccount(),
            'message' => rtrim(implode("\n", $lines)),
        ];

        if ($qrisUrl !== null && $qrisUrl !== '') {
            $payload['qris_url'] = $qrisUrl;
        }
        if ($qrisImageUrl !== null && $qrisImageUrl !== '') {
            $payload['qris_image_url'] = $qrisImageUrl;
        }

        return $payload;
    }

    /**
     * Teks rekening untuk transfer BCA saja (tanpa QRIS).
     */
    public static function bcaTransferMessage(): string
    {
        $bca = self::bcaAccount();
        if ($bca === null) {
            return '';
        }

        return trim((string) $bca['label']) . "\n"
            . trim((string) $bca['number']) . "\n"
            . 'an. ' . trim((string) ($bca['name'] !== '' ? $bca['name'] : 'LUHUR GUNAWAN'));
    }

    /**
     * @return array<string,array{label:string,number:string,name:string}>
     */
    private static function defaultAccounts(): array
    {
        return [
            'BCA' => [
                'label' => 'BCA (BANK CENTRAL ASIA)',
                'number' => '8455103793',
                'name' => 'LUHUR GUNAWAN',
            ],
            'BRI' => [
                'label' => 'BRI (BANK RAKYAT INDONESIA)',
                'number' => '327901031534535',
                'name' => 'LUHUR GUNAWAN',
            ],
        ];
    }
}
