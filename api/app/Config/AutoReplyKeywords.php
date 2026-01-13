<?php 
return [  
    'PEMBUKA' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => false,
        'patterns' => [
            '/^\s*(p|ping|ka*k|ba*n*g|b*a*pa*k|i*bu*k*|a*de*k|he*a*l+o|as+a*l+a*mu*a*l+a*i*ku*m|tes)\s*$/i',
            '/(pa*gi|so*re|si*a*ng|ma*la*m|ha*e*l+o+)\s*\b(ba*n*g|ka*k|pa*k|i*bu*k*|a*de*k*|a*na*k)/i',
            '/^\s*.\s*$/i',
        ],
        'ai_prompt' => "User HANYA memberi sapaan awal singkat tanpa permintaan/isi pesan lain.\n
        Contoh: | halo | hai | ping | pagi | siang | malam | sore | kak | bang | pak | bu |\n
        PENTING: JIKA sapaan diikuti kalimat permintaan (misal: 'Bang, baju dulukan', 'Kak, jemput ya'), ini BUKAN PEMBUKA."
    ],

    'NOTA' => [
        'max_length' => 100,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(bon|nota+|stru*k|bil+|ta*gi*ha*n|re*si)\s*$/i',
        ],
        'ai_prompt' => "User meminta:\n
        | bon | nota | struk | tagihan | bukti terima |\n
        ATAU user menanyakan TOTAL/BIAYA laundrynya, seperti:\n
        | berapa total punya saya? | berapa biaya laundry saya? | totalnya berapa? | berapa tagihan? |\n
        Jika user bertanya 'berapa' + (total/biaya/tagihan/berat) = NOTA\n
        atau yang menurut anda sangat yakin sebagai permintaan nota/bon/informasi tagihan"
    ],

    'STATUS' => [
        'max_length' => 100,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
        ],
        'ai_prompt' => "User menanyakan status/progress laundry atau kapan selesai, seperti:\n
        | sudah selesai? | bisa diambil? | kapan siap? | jam berapa siap? | sudah jadi? | jam berapa selesai? |\n
        | sudah bisa diambil? | kapan bisa diambil? | siapnya kapan? | siapnya jam berapa? |\n
        PENTING: Jika user bertanya 'kapan' atau 'jam berapa' + (siap/selesai/jadi/bisa diambil) = STATUS\n
        atau yang menurut anda sangat yakin sebagai pertanyaan status/waktu selesai laundry"
    ],

    'MINTA_JEMPUT_ANTAR' => [
        'max_length' => 100,
        'case' => 2,
        'auto_reply' => false,
        'patterns' => [
            '/^\s*(je*m*pu*t|anta*r)\s*$/i',
        ],
        'ai_prompt' => "User MEMINTA KURIR/LAUNDRY untuk datang JEMPUT atau ANTAR, ATAU menanyakan ONGKIR/TIPE PENGIRIMAN.\n
        Contoh yang ADALAH MINTA_JEMPUT_ANTAR (TRUE):\n
        | tolong jemput | minta dijemput | bisa diantar? | kapan diantar? | jam berapa diantarnya? |\n
        | bisa jemput kak? | nanti bisa jemput kak? | jemput dong | antar ya kak | dijemput ya |\n
        | brp ongkirnya? | berapa ongkosnya? | brp ong nya kak? | ongkir berapa? | biaya antar? |\n
        | kalo instant brp kak? | instant berapa? | same day brp? | express berapa? | pake gosend brp? |\n
        KUNCI DETEKSI:\n
        1. Pesan DITUJUKAN ke laundry (ada 'kak/bang/pak/kk') + kata 'jemput/antar' = TRUE\n
        2. Pertanyaan tentang ONGKIR/ONGKOS KIRIM = TRUE\n
        3. Pertanyaan tentang TIPE PENGIRIMAN (instant/same day/express/gosend/grab) = TRUE\n
        Contoh: 'brp ong nya kak' = TRUE, 'kalo instant brp kak' = TRUE\n\n
        PENGECUALIAN - BUKAN MINTA_JEMPUT_ANTAR (FALSE) jika:\n
        - Ada 'saya/aku/ku/mau' + jemput/ambil = Customer SENDIRI yang datang\n
        - Contoh: 'Mau jemput laundry kak' / 'Saya jemput nanti' / 'Ku jemput ya' = FALSE"
    ],

    'PERMINTAAN' => [
        'max_length' => 100,
        'case' => 3,
        'auto_reply' => false,
        'patterns' => [
            '/(bi*sa*|bo*le*h).*(sa*ya*|a*ku|ka*mi).*(di)?(ambi*l|je*m*pu*t)/i',
        ],
        'ai_prompt' => 'User melakukan permintaan khusus terkait laundry (waktu selesai dipercepat/didulukan, prioritas, ganti parfum, cara lipat) ATAU user mengkonfirmasi akan datang mengambil/menjemput sendiri laundrynya.'
    ],

    'JAM_OPERASIONAL' => [
        'max_length' => 30,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/(ka*pa*n|ma*si*h)\s*\b(bu*ka*|tu*tu*p)/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(bu*ka*|tu*tu*p)/i',
        ],
        'ai_prompt' => "User HANYA menanyakan jam operasional TOKO (buka/tutup), seperti:\n
        | jam berapa buka? | kapan tutup? | masih buka? | sudah tutup? |\n
        PENTING: Jika ada kata 'antar' atau 'jemput' dalam pesan = BUKAN JAM_OPERASIONAL, itu adalah MINTA_JEMPUT_ANTAR.\n
        Contoh yang BUKAN JAM_OPERASIONAL: 'jam berapa diantar?', 'kapan dijemput?', 'antar jam brp?'"
    ],

    'PENUTUP' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => false,
        'patterns' => [
            '/\bma*ka*(s|c)(i|e)*h\b/i',
            '/\bte*ri*ma*ka*si*h\b/i',
            '/\btha*nks\b/i',
            '/\b(thx|tq|ty|ok)\b/i',
            '/((hm+|ok(e*)?|sip)\s*)*(y(a*)?\s*)?(u*da*h|s*u*da*h|la+h)/i',
            '/(oh*)\s*(gi*tu+)/i',
            '/(ok|oh).*(siap|sip|ok)/i',
        ],
        'ai_prompt' => "Penutup seperti:\n
        | terima kasih | ok deh | siap kak | iya lah kak |\nAtau hanya mengkonfirmasi bahwa:\n
        | sudah lunas | sudah diambil | akan menjemput | akan mengantarkan |"
    ],

    'REMINDER' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(reminder|remind|ingatkan|ingat|pengingat)\s*$/i',
        ],
    ],

    'KAS_LAUNDRY' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(kas|saldo)\s*(laundry|laundri|londri|loundry|loundri)\s*$/i',
        ],
    ],

    'TOKEN_LIST' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(token|pln)\s*(list|data)\s*$/i',
        ],
    ],

    'TOKEN_BUY' => [
        'max_length' => 20,
        'case' => 0,
        'auto_reply' => true,
        'patterns' => [
            '/^token \d+$/i',
        ],
    ],
];
