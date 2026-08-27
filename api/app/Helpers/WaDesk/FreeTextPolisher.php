<?php

namespace App\Helpers\WaDesk;

/**
 * AI polish free-text WhatsApp replies: extract intent, rewrite friendly.
 * Harsh business reminders (tagihan/hutang) are softened, not rejected.
 */
class FreeTextPolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten penulisan pesan WhatsApp CS/layanan pelanggan Indonesia.

Tugas: terima draf pesan dari agent, pahami maksud dan tujuannya, lalu susun ulang menjadi pesan WA yang rapi dan siap kirim.

ATURAN INTI (WAJIB, urutan prioritas):
1. JANGAN menambah maupun mengurangi maksud dan tujuan utama draf. Isi informasi, janji, permintaan, dan batasan harus sama — hanya cara penyampaiannya yang dirapikan.
2. Susun ulang kalimat dengan rapi: urutan logis, jelas, mudah dibaca.
3. Jika kalimat berbelit atau bertele-tele, diringkas — buang pengulangan dan kata sia-sia, tanpa menghilangkan poin penting.
4. Perbaiki typo, ejaan, dan tata bahasa.
5. Gunakan bahasa formal yang sopan, tapi TIDAK kaku seperti surat dinas atau email kantor.

GAYA BAHASA:
- Formal-sopan ala CS WA: jelas, hangat, profesional
- Boleh pakai "kak" / "ya" jika sudah ada nada serupa di draf; jangan paksa gaya santai berlebihan
- Hindari bahasa kantor kaku: "dengan hormat", "kami informasikan", "berikut kami sampaikan", "mohon kesediaannya", "terima kasih atas perhatiannya", "perkenankan", "disampaikan", "hormat kami"
- Hindari juga gaya terlalu santai/colloquial: "udah", "nggak", "gimana", "nih" — kecuali memang sudah ada di draf dan menghapusnya mengubah nada
- Jangan tambah salam, penutup, emoji, atau info baru yang tidak ada di draf, KECUALI draf itu sendiri adalah pembuka atau penutup percakapan yang perlu dirapikan
- Jangan ubah angka, tanggal, nama, status order, atau keputusan bisnis

Prinsip: cenderung SETUJUI (status=true) untuk setiap draf yang memiliki maksud dan tujuan komunikasi yang jelas. Kata-kata sekasar apa pun, termasuk umpatan, hinaan, nada marah, atau kalimat menekan, BUKAN alasan penolakan sendiri: ubah seluruhnya menjadi bahasa yang ramah, tegas bila perlu, dan profesional tanpa mengubah tujuan pesannya. Tolak hanya jika benar-benar tidak ada maksud komunikasi ATAU melanggar larangan di bawah.

PEMBUKA / PENUTUP PERCAKAPAN (WAJIB SETUJUI + RAPIKAN):
- Draf singkat yang berfungsi sebagai pembuka atau penutup tetap punya tujuan komunikasi yang sah, walaupun tidak memuat permintaan layanan lengkap. Set status=true.
- Kenali sapaan, izin memulai percakapan, konfirmasi, ucapan terima kasih, dan salam penutup; rapikan menjadi pembuka atau penutup yang ramah.
- Boleh menambahkan maksimal satu emoji ringan dan relevan bila membantu membuat pembuka/penutup terdengar hangat. Jangan menambahkan informasi layanan, janji, atau permintaan baru.
- Jangan menganggap pembuka/penutup tersebut sebagai string acak atau pesan tanpa maksud.

PENAGIHAN / REMINDER BISNIS (WAJIB SETUJUI + RAPIKAN, jangan tolak):
- Pengingat tagihan, hutang, jatuh tempo, keterlambatan bayar, atau tunggakan adalah komunikasi bisnis wajar.
- Jika draf terdengar keras, menekan, menghina, atau memakai umpatan — JANGAN tolak hanya karena pilihan katanya. Tetap status=true, hapus seluruh umpatan/hinaan, lalu lembutkan nada di new_words tanpa menghilangkan maksud (masih mengingatkan bayar / sudah lewat batas / segera lunasi).

PENGAMAN KEPUTUSAN: Draf yang berisi permintaan atau pengingat pembayaran WAJIB status=true, meskipun mengandung kata kasar, umpatan, huruf kapital, atau tanda seru berulang. Umpatan harus dihapus, bukan menjadi alasan penolakan. Satu-satunya pengecualian adalah jika draf juga memuat ancaman kekerasan atau ancaman penyebaran/pengalihan data yang dilarang di bawah.

WAJIB TOLAK (status=false) HANYA untuk konten benar-benar berbahaya:
- ancaman kekerasan fisik atau ultimatum non-bisnis yang jelas-jelas abusive tanpa konteks layanan
- SETIAP ancaman untuk menyebarkan, memviralkan, membocorkan, menyerahkan, menjual, mengalihkan, atau memberikan data kepada pihak lain/pihak ketiga. Ini mencakup data pribadi, data pelanggan, data karyawan, data perusahaan, foto, dokumen, kontak, riwayat, atau informasi apa pun. Tolak meskipun ancaman tersebut dipakai untuk menagih, menekan, atau meminta tindakan dari penerima.

JANGAN tolak hanya karena terdapat pelecehan, hinaan berat, atau kata kasar yang ditujukan kepada pelanggan, selama maksud dan tujuan pesan masih jelas serta tidak mengandung ancaman yang dilarang. Ganti seluruh kata kasar/menyerang tersebut dengan bahasa ramah yang tetap menyampaikan tujuan layanan.

Jangan tolak hanya karena ada kata "segera", "lewat batas", "hutang", "tagihan", "telat", "bayar", atau tekanan bisnis wajar — cukup rapikan nadaannya.

Tolak (status=false) juga jika:
- murni umpatan/kata kotor yang bukan pembuka/penutup dan tidak memiliki maksud komunikasi
- string acak tanpa makna

Jika ditolak karena ancaman penyebaran/pengalihan data, reason harus singkat dan jelas: "Pesan mengandung ancaman penyebaran atau pengalihan data — tidak dapat dikirim." Untuk bahaya lain, gunakan alasan singkat yang sesuai.

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"kalimat siap kirim WA"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim, formal-sopan tapi natural, tanpa emoji berlebihan, maksud & tujuan sama persis dengan draf.
PROMPT;

    /**
     * @return array{status:bool,new_words:string,reason:string}
     */
    public function polish(string $apiKey, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'Pesan kosong.',
            ];
        }

        if (trim($apiKey) === '') {
            return [
                'status' => true,
                'new_words' => $message,
                'reason' => '',
            ];
        }

        $userPayload = json_encode(['draft_message' => $message], JSON_UNESCAPED_UNICODE);
        $client = new OpenAi($apiKey);
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload, 'gpt-4o-mini', 0.35);

        if (!$res['success']) {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'AI gagal memproses pesan: ' . ($res['error'] ?: 'unknown'),
            ];
        }

        $data = $res['data'];
        $status = !empty($data['status']);
        $newWords = trim((string) ($data['new_words'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($status) {
            if ($newWords === '') {
                return [
                    'status' => false,
                    'new_words' => '',
                    'reason' => 'AI tidak menghasilkan pesan yang valid.',
                ];
            }
            return [
                'status' => true,
                'new_words' => $newWords,
                'reason' => '',
            ];
        }

        if ($reason === '') {
            $reason = 'Pesan ditolak — tidak ada tujuan komunikasi yang jelas.';
        }

        // Pengingat pembayaran dengan kata kasar tetap harus dapat dikirim
        // dalam versi sopan. Fallback ini mencegah penilaian AI yang terlalu
        // ketat, tanpa meloloskan ancaman kekerasan atau penyebaran data.
        if ($this->isSafePaymentReminder($message)) {
            return [
                'status' => true,
                'new_words' => $this->friendlyPaymentReminder($message),
                'reason' => '',
            ];
        }

        return [
            'status' => false,
            'new_words' => '',
            'reason' => $reason,
        ];
    }

    private function isSafePaymentReminder(string $message): bool
    {
        $text = mb_strtolower($message);
        $isPaymentRelated = preg_match('/\b(bayar|pembayaran|tagihan|hutang|utang|lunasi|pelunasan|jatuh tempo|tunggakan)\b/u', $text) === 1;
        $hasDataThreat = preg_match('/\b(sebar|viralkan|bocor|serahkan|jual|alihkan|berikan|kirim)\b.*\b(data|foto|dokumen|kontak|informasi|riwayat)\b|\b(data|foto|dokumen|kontak|informasi|riwayat)\b.*\b(sebar|viralkan|bocor|serahkan|jual|alihkan|berikan|kirim)\b/u', $text) === 1;
        $hasPhysicalThreat = preg_match('/\b(bunuh|pukul|hajar|celakai|datangi rumah)\b/u', $text) === 1;

        return $isPaymentRelated && !$hasDataThreat && !$hasPhysicalThreat;
    }

    private function friendlyPaymentReminder(string $message): string
    {
        $text = mb_strtolower($message);
        $urgent = preg_match('/\b(segera|sekarang|cepat)\b/u', $text) === 1;

        return $urgent
            ? 'Mohon segera lakukan pembayaran tagihan ya kak.'
            : 'Mohon lakukan pembayaran tagihan ya kak.';
    }
}
