<?php

/**
 * Resolve nomor WA dari id_pelanggan (kolom notif.id_pelanggan / wa_messages_out.id_pelanggan).
 */
class NotifRecipient
{
    public static function phoneById($db, int $idPelanggan): ?string
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        $row = $db->get_where_row('pelanggan', 'id_pelanggan = ' . $idPelanggan);
        if (!is_array($row)) {
            return null;
        }
        $nomor = trim((string) ($row['nomor_pelanggan'] ?? ''));
        if ($nomor === '' || $nomor === '0') {
            return null;
        }

        return $nomor;
    }

    /** Nomor alternatif (nomor_pelanggan_2) atau null jika kosong. */
    public static function secondPhoneById($db, int $idPelanggan): ?string
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        $row = $db->get_where_row('pelanggan', 'id_pelanggan = ' . $idPelanggan);
        if (!is_array($row)) {
            return null;
        }
        $nomor = trim((string) ($row['nomor_pelanggan_2'] ?? ''));
        if ($nomor === '' || $nomor === '0') {
            return null;
        }

        return $nomor;
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
            $variants[] = '628' . substr($digits, 2);
            $variants[] = '+628' . substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $variants[] = '62' . substr($digits, 1);
            $variants[] = '+62' . substr($digits, 1);
            $variants[] = '628' . substr($digits, 2);
            $variants[] = '+628' . substr($digits, 2);
        } else {
            $variants[] = '62' . $digits;
            $variants[] = '+62' . $digits;
            $variants[] = '0' . $digits;
        }

        return array_values(array_unique($variants));
    }
}
