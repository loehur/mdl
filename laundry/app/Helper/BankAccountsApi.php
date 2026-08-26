<?php

/**
 * Data rekening pembayaran Laundry.
 *
 * Jangan menaruh nomor rekening di aplikasi Laundry: sumber tunggalnya adalah
 * Env::BCA_PAYMENT_ACCOUNTS pada API melalui Payment/BankAccounts/index.
 */
class BankAccountsApi
{
    /** @var array<string,array{label:string,number:string,name:string}>|null */
    private static $accounts = null;

    /**
     * @return array<string,array{label:string,number:string,name:string}>
     */
    public static function accounts(): array
    {
        if (self::$accounts !== null) {
            return self::$accounts;
        }

        self::$accounts = [];
        if (!function_exists('curl_init')) {
            return self::$accounts;
        }

        $url = ApiLoopback::baseUrl() . '/Payment/BankAccounts/index';
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ApiLoopback::headers($url, ['Accept: application/json']),
        ];
        curl_setopt_array($curl, ApiLoopback::curlOpts($url, $options));
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($raw === false || $status < 200 || $status >= 300) {
            return self::$accounts;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['ok']) || !is_array($payload['accounts'] ?? null)) {
            return self::$accounts;
        }

        foreach ($payload['accounts'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $number = trim((string) ($row['number'] ?? ''));
            if ($code === '' || $number === '') {
                continue;
            }
            self::$accounts[$code] = [
                'label' => trim((string) ($row['label'] ?? $code)),
                'number' => $number,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return self::$accounts;
    }

    /** @return array{label:string,number:string,name:string}|null */
    public static function bcaAccount(): ?array
    {
        $accounts = self::accounts();
        return $accounts['BCA'] ?? null;
    }
}
