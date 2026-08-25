<?php

namespace App\Helpers\CRM;

/**
 * Resolve nomor WA dari id_pelanggan (mdl_laundry.pelanggan).
 */
class NotifRecipient
{
    /**
     * @param \App\Core\DB $dbLaundry db(1)
     */
    public static function phoneById($dbLaundry, int $idPelanggan): ?string
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        try {
            $q = $dbLaundry->query(
                'SELECT nomor_pelanggan, nomor_pelanggan_2 FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            );
            if (!$q || $q->num_rows() <= 0) {
                return null;
            }
            $row = $q->row_array();
            $nomor = trim((string) ($row['nomor_pelanggan'] ?? ''));
            if ($nomor === '' || $nomor === '0') {
                $nomor = trim((string) ($row['nomor_pelanggan_2'] ?? ''));
            }
            if ($nomor === '' || $nomor === '0') {
                return null;
            }

            return $nomor;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return list<string> */
    public static function phoneLookupVariants(string $phone): array
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === null || strlen($digits) < 8) {
            return [];
        }
        $variants = [$digits, '+' . $digits];
        if (str_starts_with($digits, '62')) {
            $local = substr($digits, 2);
            $variants[] = '0' . $local;
            $variants[] = $local;
        } elseif (str_starts_with($digits, '0')) {
            $variants[] = '62' . substr($digits, 1);
            $variants[] = '+62' . substr($digits, 1);
        } else {
            $variants[] = '62' . $digits;
            $variants[] = '+62' . $digits;
            $variants[] = '0' . $digits;
        }

        return array_values(array_unique($variants));
    }

    /**
     * Nilai untuk match baris wa_messages_out penerima yang sama.
     *
     * @param \App\Core\DB $dbLaundry db(1)
     * @return list<string>
     */
    public static function waOutPhoneMatchValues($dbLaundry, int $idPelanggan, ?string $resolvedPhone = null): array
    {
        $values = [];
        if ($idPelanggan > 0) {
            $values[] = (string) $idPelanggan;
        }
        $phone = $resolvedPhone ?? self::phoneById($dbLaundry, $idPelanggan);
        if ($phone !== null && $phone !== '') {
            foreach (self::phoneLookupVariants($phone) as $variant) {
                $values[] = $variant;
            }
        }

        return array_values(array_unique($values));
    }
}
