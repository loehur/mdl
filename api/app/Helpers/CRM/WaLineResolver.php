<?php

namespace App\Helpers\CRM;

use App\Config\WaLines;

/**
 * Resolve business_phone / line_key dari webhook YCloud atau request API.
 */
class WaLineResolver
{
    /**
     * @return array{key:string,phone:string,short_label:string,display_name:string}|null
     */
    public static function fromBusinessPhone(?string $raw): ?array
    {
        $normalized = WaLines::normalizeE164((string) $raw);
        if ($normalized === '') {
            return null;
        }

        foreach (WaLines::all() as $line) {
            if ($line['phone'] === $normalized) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @return array{key:string,phone:string,short_label:string,display_name:string}
     */
    public static function fromBusinessPhoneOrDefault(?string $raw): array
    {
        return self::fromBusinessPhone($raw) ?? WaLines::defaultLine();
    }

    /**
     * @return array{key:string,phone:string,short_label:string,display_name:string}|null
     */
    public static function fromLineKey(?string $lineKey): ?array
    {
        if ($lineKey === null || trim($lineKey) === '') {
            return null;
        }

        return WaLines::get(strtolower(trim($lineKey)));
    }

    /**
     * Accept line_key, business_phone, or legacy channel names (ycloud/fonnte/admin/cs).
     *
     * @return array{key:string,phone:string,short_label:string,display_name:string}|null
     */
    public static function fromRequest(?string $requested): ?array
    {
        if ($requested === null) {
            return null;
        }
        $req = strtolower(trim($requested));
        if ($req === '' || $req === 'auto') {
            return null;
        }

        $byKey = self::fromLineKey($req);
        if ($byKey) {
            return $byKey;
        }

        $legacyMap = [
            'ycloud' => WaLines::KEY_CS,
            'fonnte' => WaLines::KEY_ADMIN,
            'y' => WaLines::KEY_CS,
            'f' => WaLines::KEY_ADMIN,
            'a' => WaLines::KEY_ADMIN,
            'b' => WaLines::KEY_CS,
        ];
        if (isset($legacyMap[$req])) {
            return WaLines::get($legacyMap[$req]);
        }

        return self::fromBusinessPhone($requested);
    }

    public static function messageIdPrefix(string $lineKey): string
    {
        $line = WaLines::get($lineKey);

        return $line ? $line['key'] : WaLines::defaultLine()['key'];
    }

    /** @return array<string, mixed> */
    public static function messageApiFields(string $lineKey): array
    {
        $meta = WaLines::lineMeta($lineKey);

        return $meta ?? WaLines::lineMeta(WaLines::defaultLine()['key']);
    }
}
