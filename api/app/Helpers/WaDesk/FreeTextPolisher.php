<?php

namespace App\Helpers\WaDesk;

/**
 * AI polish free-text WhatsApp replies: extract intent, rewrite friendly.
 * Harsh business reminders (tagihan/hutang) are softened, not rejected.
 */
class FreeTextPolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten penulisan pesan WhatsApp bisnis Indonesia.

Tugas: terima RINGKASAN PERCAKAPAN dan draf pesan dari agent. Sebelum menulis, WAJIB tentukan satu peran yang paling sesuai berdasarkan kedua konteks itu:
- "collection_agent": pembahasan tagihan, pembayaran, tunggakan, pelunasan, atau penagihan.
- "promotor": seluruh konteks bisnis lain, termasuk promosi, informasi produk/layanan, tindak lanjut, dan layanan pelanggan.

Setelah menentukan peran, susun ulang draf menjadi pesan WA yang rapi dan siap kirim. Jangan menyebut nama peran kepada pelanggan.

ATURAN INTI (WAJIB, urutan prioritas):
1. JANGAN menambah maupun mengurangi maksud dan tujuan utama draf. Isi informasi, janji, permintaan, dan batasan harus sama — hanya cara penyampaiannya yang dirapikan.
2. Susun ulang kalimat dengan rapi: urutan logis, jelas, mudah dibaca.
3. Jika kalimat berbelit atau bertele-tele, diringkas — buang pengulangan dan kata sia-sia, tanpa menghilangkan poin penting.
4. Perbaiki typo, ejaan, dan tata bahasa.
5. Gunakan bahasa formal yang sopan, tapi TIDAK kaku seperti surat dinas atau email kantor.
6. Jika draf memuat placeholder template seperti {{customer_name}}, pertahankan setiap placeholder itu persis sama; jangan menghapus, mengganti nama, menambah, atau memindahkan urutannya.

GAYA BAHASA:
- Formal-sopan ala CS WA: jelas, hangat, profesional
- Bila perannya collection_agent: tegas tetapi tetap empatik, tidak mengintimidasi atau mempermalukan pelanggan.
- Tujuan penagihan adalah membantu pelanggan menyelesaikan kewajibannya, bukan membuatnya takut. Utamakan ajakan yang jelas, empatik, dan menawarkan langkah penyelesaian bila relevan dari draf.
- Bila perannya promotor: hangat dan meyakinkan, tetapi jangan mengarang promo, harga, manfaat, atau janji baru.
- Boleh pakai "kak" / "ya" jika sudah ada nada serupa di draf; jangan paksa gaya santai berlebihan
- Hindari bahasa kantor kaku: "dengan hormat", "kami informasikan", "berikut kami sampaikan", "mohon kesediaannya", "terima kasih atas perhatiannya", "perkenankan", "disampaikan", "hormat kami"
- Hindari juga gaya terlalu santai/colloquial: "udah", "nggak", "gimana", "nih" — kecuali memang sudah ada di draf dan menghapusnya mengubah nada
- Jangan tambah salam, penutup, emoji, atau info baru yang tidak ada di draf, KECUALI draf itu sendiri adalah pembuka atau penutup percakapan yang perlu dirapikan
- Jangan ubah angka, tanggal, nama, status order, atau keputusan bisnis
- Ganti bahasa yang menyalahkan atau menyinggung pelanggan, misalnya "beretika", "tidak beretika", "sopan santun", "tidak sopan", "tidak kooperatif", atau sejenisnya, menjadi bahasa netral yang fokus pada bantuan dan langkah berikutnya. Jangan menggurui atau menilai karakter pelanggan.

Prinsip: cenderung SETUJUI (status=true) untuk setiap draf yang memiliki maksud dan tujuan komunikasi yang jelas. Kata-kata sekasar apa pun, termasuk umpatan, hinaan, nada marah, atau kalimat menekan, BUKAN alasan penolakan sendiri: ubah seluruhnya menjadi bahasa yang ramah, tegas bila perlu, dan profesional tanpa mengubah tujuan pesannya. Tolak hanya jika benar-benar tidak ada maksud komunikasi yang dapat dipahami.

PEMBUKA / PENUTUP PERCAKAPAN (WAJIB SETUJUI + RAPIKAN):
- Draf singkat yang berfungsi sebagai pembuka atau penutup tetap punya tujuan komunikasi yang sah, walaupun tidak memuat permintaan layanan lengkap. Set status=true.
- Kenali sapaan, izin memulai percakapan, konfirmasi, ucapan terima kasih, dan salam penutup; rapikan menjadi pembuka atau penutup yang ramah.
- Boleh menambahkan maksimal satu emoji ringan dan relevan bila membantu membuat pembuka/penutup terdengar hangat. Jangan menambahkan informasi layanan, janji, atau permintaan baru.
- Jangan menganggap pembuka/penutup tersebut sebagai string acak atau pesan tanpa maksud.

PENAGIHAN / REMINDER BISNIS (WAJIB SETUJUI + RAPIKAN, jangan tolak):
- Pengingat tagihan, hutang, jatuh tempo, keterlambatan bayar, atau tunggakan adalah komunikasi bisnis wajar.
- Jika draf terdengar keras, menekan, menghina, atau memakai umpatan — JANGAN tolak hanya karena pilihan katanya. Tetap status=true, hapus seluruh umpatan/hinaan, lalu lembutkan nada di new_words tanpa menghilangkan maksud (masih mengingatkan bayar / sudah lewat batas / segera lunasi).

PENGAMAN KEPUTUSAN: Draf yang berisi permintaan atau pengingat pembayaran WAJIB status=true, meskipun mengandung kata kasar, umpatan, huruf kapital, tanda seru berulang, atau tekanan. Hapus unsur menyerang/menakut-nakuti; ubah menjadi ajakan penyelesaian yang ramah. Jangan mempertahankan ancaman kekerasan, penyebaran data, atau ultimatum di new_words—ganti dengan pengingat pembayaran yang netral bila maksud penagihan masih jelas.

TRANSFORMASI TEKANAN:
- Jika draf memakai kata/variasi: "sita" atau "penyitaan", "lapor" atau "melaporkan", "blacklist" atau "daftar hitam", "pidana", "konsekuensi", "OJK", atau "denda" sebagai tekanan, JANGAN pertahankan kata maupun ancamannya di new_words. Ubah menjadi pengingat netral yang membantu pelanggan menyelesaikan pembayaran. Jangan menolak hanya karena istilah tersebut ada, selama maksud komunikasi masih jelas.

JANGAN tolak hanya karena terdapat pelecehan, hinaan berat, kata kasar, atau tekanan yang ditujukan kepada pelanggan, selama maksud dan tujuan pesan masih jelas. Ganti seluruh kata kasar/menyerang/menakut-nakuti tersebut dengan bahasa ramah yang tetap menyampaikan tujuan layanan.

Jangan tolak hanya karena ada kata "segera", "lewat batas", "hutang", "tagihan", "telat", "bayar", atau tekanan bisnis wajar — cukup rapikan nadaannya.

Tolak (status=false) juga jika:
- murni umpatan/kata kotor yang bukan pembuka/penutup dan tidak memiliki maksud komunikasi
- string acak tanpa makna

Tolak hanya jika draf murni tidak memiliki maksud komunikasi yang dapat dipahami, misalnya string acak atau umpatan tanpa tujuan. Untuk penolakan, gunakan alasan singkat yang sesuai.

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"role":"collection_agent","new_words":"kalimat siap kirim WA"}
atau
{"status":false,"role":"promotor","reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim, formal-sopan tapi natural, tanpa emoji berlebihan, maksud & tujuan sama persis dengan draf.
PROMPT;

    /**
     * @return array{status:bool,new_words:string,reason:string,role:string}
     */
    public function polish(string $apiKey, string $message, string $conversationSummary = ''): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'Pesan kosong.',
                'role' => 'promotor',
            ];
        }

        if (trim($apiKey) === '') {
            return [
                'status' => true,
                'new_words' => $message,
                'reason' => '',
                'role' => 'promotor',
            ];
        }

        $userPayload = json_encode([
            'conversation_summary' => trim($conversationSummary) ?: '(Belum ada riwayat percakapan.)',
            'draft_message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        $client = new OpenAi($apiKey);
        // WaDesk free-text polishing intentionally uses its own model and
        // must not follow the global Env::OPENAI_MODEL setting.
        $model = 'gpt-4o';
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload, $model, 0.35);

        if (!$res['success']) {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'AI gagal memproses pesan: ' . ($res['error'] ?: 'unknown'),
                'role' => 'promotor',
            ];
        }

        $data = $res['data'];
        $status = !empty($data['status']);
        $newWords = trim((string) ($data['new_words'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $role = strtolower(trim((string) ($data['role'] ?? '')));
        if (!in_array($role, ['collection_agent', 'promotor'], true)) {
            $role = $this->looksLikeCollectionContext($message, $conversationSummary) ? 'collection_agent' : 'promotor';
        }

        if ($status) {
            if ($newWords === '') {
                return [
                    'status' => false,
                    'new_words' => '',
                    'reason' => 'AI tidak menghasilkan pesan yang valid.',
                    'role' => $role,
                ];
            }
            return [
                'status' => true,
                'new_words' => $newWords,
                'reason' => '',
                'role' => $role,
            ];
        }

        if ($reason === '') {
            $reason = 'Pesan ditolak — tidak ada tujuan komunikasi yang jelas.';
        }

        // Pengingat pembayaran dengan kata kasar atau tekanan dapat disederhanakan
        // secara sopan; penolakan AI yang terlalu ketat tidak menghentikan pesan.
        if ($this->isSafePaymentReminder($message)) {
            return [
                'status' => true,
                'new_words' => $this->friendlyPaymentReminder($message),
                'reason' => '',
                'role' => 'collection_agent',
            ];
        }

        return [
            'status' => false,
            'new_words' => '',
            'reason' => $reason,
            'role' => $role,
        ];
    }

    private function looksLikeCollectionContext(string $message, string $summary): bool
    {
        return preg_match('/\b(bayar|pembayaran|tagihan|hutang|utang|lunasi|pelunasan|jatuh tempo|tunggakan)\b/iu', $message . ' ' . $summary) === 1;
    }

    private function isSafePaymentReminder(string $message): bool
    {
        $text = mb_strtolower($message);
        return preg_match('/\b(bayar|pembayaran|tagihan|hutang|utang|lunasi|pelunasan|jatuh tempo|tunggakan)\b/u', $text) === 1;
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
