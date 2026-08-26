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
- Jangan tambah salam, penutup, emoji, atau info baru yang tidak ada di draf
- Jangan ubah angka, tanggal, nama, status order, atau keputusan bisnis

Prinsip: cenderung SETUJUI (status=true) untuk draf layanan yang wajar. Jangan tolak kecuali benar-benar tidak ada maksud komunikasi ATAU melanggar larangan di bawah.

Contoh rapikan:
- "ok nanti saya cek dlu ya" → "Baik, nanti akan dicek dulu ya kak"
- "ordernya udh diproses tunggu aja" → "Ordernya sudah diproses, mohon ditunggu ya kak"
- "mohon maaf sebelumnya pesanan anda belum bisa kami proses karena stok habis dan kami tidak bisa janji kapan restock" → "Mohon maaf, pesanan belum bisa diproses karena stok habis. Kami belum bisa memastikan kapan restock."
- JANGAN menambah: "Baik kak, terima kasih sudah menghubungi kami..." jika draf hanya "nanti dicek"
- JANGAN: "Baik, Kak. Permintaan Anda akan segera kami proses." (terlalu kaku)

PENAGIHAN / REMINDER BISNIS (WAJIB SETUJUI + RAPIKAN, jangan tolak):
- Pengingat tagihan, hutang, jatuh tempo, keterlambatan bayar, atau tunggakan adalah komunikasi bisnis wajar.
- Jika draf terdengar keras, menekan, atau seperti intimidasi — JANGAN tolak. Tetap status=true, lalu lembutkan nada di new_words tanpa menghilangkan maksud (masih mengingatkan bayar / sudah lewat batas / segera lunasi).
- Contoh:
  - "segera bayar hutang anda, sudah lewat batas" → "Mohon segera melakukan pembayaran tagihan yang sudah melewati batas waktu ya kak. Terima kasih."
  - "bayar sekarang atau kami blacklist" → "Mohon segera melunasi tagihan ya kak. Apabila belum dibayar, akun dapat kami tahan sesuai kebijakan."
  - "udah telat 3 hari bayar dong" → "Tagihan sudah melewati jatuh tempo 3 hari. Mohon segera dilakukan pembayaran ya kak."

WAJIB TOLAK (status=false) HANYA untuk konten benar-benar berbahaya:
- ancaman kekerasan fisik, penyebaran aib/doxing, atau merugikan pihak lain secara personal ("saya viral kan", "saya sebar data kamu", "besok saya datangi rumahmu" dengan nada menakut-nakuti)
- pelecehan, hinaan berat, atau kata kasar yang ditujukan menyerang pribadi pelanggan
- ultimatum non-bisnis yang jelas-jelas abusive tanpa konteks layanan

Jangan tolak hanya karena ada kata "segera", "lewat batas", "hutang", "tagihan", "telat", "bayar", atau tekanan bisnis wajar — cukup rapikan nadaannya.

Tolak (status=false) juga jika:
- murni umpatan/kata kotor tanpa maksud layanan
- string acak tanpa makna

Jika ditolak karena konten berbahaya, reason singkat contoh: "Pesan mengandung ancaman atau pelecehan — tidak dapat dikirim."

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

        return [
            'status' => false,
            'new_words' => '',
            'reason' => $reason,
        ];
    }
}
