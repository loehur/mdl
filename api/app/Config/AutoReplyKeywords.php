<?php 
return [  
    'PEMBUKA' => [
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
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
        ],
        'ai_prompt' => "User menanyakan status/progress laundry atau kapan selesai, seperti:\n
        | sudah selesai? | bisa diambil? | kapan siap? | jam berapa siap? | sudah jadi? | jam berapa selesai? |\n
        | sudah bisa diambil? | kapan bisa diambil? | siapnya kapan? | siapnya jam berapa? |\n
        PENTING: Jika user bertanya 'kapan' atau 'jam berapa' + (siap/selesai/jadi/bisa diambil) = STATUS\n
        atau yang menurut anda sangat yakin sebagai pertanyaan status/waktu selesai laundry"
    ],

    'HARGA' => [
        'patterns' => [
            '/^\s*(harga|price)\s*$/i',
        ],
        'ai_prompt' => "User menanyakan harga/biaya laundry PER ITEM atau PER KILO, seperti:\n
        | berapa harga? | berapa biaya? | harga berapa? | biaya berapa? |\n
        | berapa harga baju? | berapa harga celana? | berapa harga handuk? | berapa harga boneka? | berapa harga sepatu? | berapa harga selimut? | berapa harga jaket? |\n
        | berapa harga per kilo? | berapa biaya per kg? | harga per kilo berapa? |\n
        PENTING: \n
        - Jika user bertanya 'berapa' + (harga/biaya) + (item laundry atau per kilo) = CEK_HARGA\n
        - Jika user bertanya 'berapa' + (ongkir/ongkos/biaya antar/biaya jemput) = BUKAN CEK_HARGA, itu adalah MINTA_JEMPUT_ANTAR\n
        - Jika user bertanya 'berapa' + (berat) = BUKAN CEK_HARGA, itu mungkin NOTA atau pertanyaan lain\n
        atau yang menurut anda sangat yakin sebagai pertanyaan harga/biaya laundry PER ITEM atau PER KILO"
    ],

   'MINTA_JEMPUT_ANTAR' => [
      'case' => 2,
      'notify' => true,
      'patterns' => [
         '/^\s*(je*m*pu*t|anta*r)\s*$/i',
      ],
      'ai_prompt' => "User MEMINTA KURIR/LAUNDRY untuk datang JEMPUT atau ANTAR, ATAU menanyakan ONGKIR.\n
      TRUE (MINTA JEMPUT/ANTAR) - HARUS ADA KATA PERMINTAAN/PERTANYAAN:\n
      - Kata kunci: tolong/minta/bisa/boleh/dong/kapan/berapa + jemput/antar\n
      - tolong jemput, minta dijemput, bisa diantar?, boleh dijemput?, kapan diantar?\n
      - bisa jemput kak?, nanti bisa jemput kak?, jemput dong, antar ya dong\n
      - brp ongkirnya?, berapa ongkosnya?, brp ong nya kak?, biaya antar?\n
      \n
      FALSE (BUKAN MINTA JEMPUT/ANTAR) - SANGAT PENTING:\n
      - User yang akan MENGAMBIL SENDIRI: | mau jemput | saya jemput | aku ambil | awak jemput | nanti saya jemput |\n
      - Konfirmasi/Pemberitahuan: | baik nanti dijemput | ok sore diantar | iya nanti akan dijemput | siap dijemput |\n
      - Hanya memberitahu jadwal tanpa permintaan: | nanti sore dijemput | besok diantar | jam 2 dijemput |\n
      - CRITICAL: 'Mau jemput' = User SENDIRI yang akan mengambil = FALSE\n
      - CRITICAL: Jika TIDAK ada kata tolong/minta/bisa/boleh/dong/kapan/ = FALSE\n
      - Contoh FALSE: 'Mau jemput,jgn tutup dlu' (user akan ambil sendiri, bukan minta kurir)\n
      - Contoh FALSE: 'Baikla nnti sore dijemput ya kak' (ini konfirmasi)\n
      - Contoh FALSE: 'Ok dijemput ya' (ini konfirmasi)\n
      - Contoh FALSE: 'Saya jemput nanti' (user akan ambil sendiri)"
   ],

    'PERMINTAAN' => [
        'case' => 3,
        'notify' => true,
        'patterns' => [
            '/(bi*sa*|bo*le*h).*(sa*ya*|a*ku|ka*mi).*(di)?(ambi*l|je*m*pu*t)/i',
            '/(bantu|tolong|minta|bisa)(?!.*(antar|jemput)).*(baju|pakaian|celana|handuk|boneka|sepatu|selimut|jaket)/i',
        ],
        'ai_prompt' => "User melakukan PERMINTAAN KHUSUS atau INSTRUKSI KHUSUS terkait laundry.\n
        TRUE jika:\n
        - Permintaan treatment khusus: | bantu dibersihkan | tolong difokusin | baju ini dicuci khusus | noda ini dihilangkan |\n
        - Permintaan waktu/prioritas: | tolong dipercepat | didulukan ya | kapan bisa selesai | prioritas dong |\n
        - Permintaan cara treatment: | ganti parfum | jangan pakai pelembut | lipat rapi | setrika aja |\n
        - Konfirmasi ambil sendiri: | saya jemput nanti | aku ambil sendiri | nanti sore saya datang |\n
        - Ada kata: bantu/tolong/minta/bisa + object laundry (baju/celana/handuk/dll)\n
        \n
        FALSE jika:\n
        - Hanya sapaan: 'halo' tanpa permintaan\n
        - Hanya tanya status: 'kapan siap?' tanpa instruksi khusus\n
        - Minta jemput/antar: 'tolong jemput' (ini MINTA_JEMPUT_ANTAR)\n
        - Pemberitahuan singkat: 'Mau jemput,jgn tutup dlu' (terlalu singkat/informal)"
    ],

    'JAM_OPERASIONAL' => [
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
      'patterns' => [
         '/\bma*ka*(s|c)(i|e)*h\b/i',
         '/\bte*ri*ma*ka*si*h\b/i',
         '/\btha*nks\b/i',
         '/\b(thx|tq|ty|ok)\b/i',
         '/((hm+|ok(e*)?|sip)\s*)*(y(a*)?\s*)?(u*da*h|s*u*da*h|la+h)/i',
         '/(oh*)\s*(gi*tu+)/i',
         '/(ok|oh).*(siap|sip|ok)/i',
         '/^reacted\s+[^\s]+$/i', // WhatsApp reactions: "Reacted ❤️", "Reacted 👍"
      ],
      'ai_prompt' => "User memberikan PENUTUP/CLOSING/ACKNOWLEDGMENT tanpa pertanyaan atau permintaan lanjutan.\n
      TRUE jika:\n
      - Ucapan terima kasih: | terima kasih | makasih | thanks | thx |\n
      - Konfirmasi singkat: | ok deh | siap kak | iya lah kak | sudah | oke | baik |\n
      - Konfirmasi status: | sudah lunas | sudah diambil |\n
      - Konfirmasi jadwal (TANPA PERMINTAAN): | baik nanti dijemput | ok sore diantar | iya nanti saya jemput | siap dijemput ya |\n
      - Pemberitahuan jadwal: | nanti sore dijemput | besok diantar | jam 2 dijemput ya kak |\n
      - EMOJI/REACTION SAJA: | ❤️ | 👍 | 🙏 | 👌 | ✅ | atau kata 'Reacted' + emoji |\n
      - Single emoji tanpa text apapun\n
      - Kata kunci: baik/ok/iya/siap + (nanti/sore/besok/jam) + dijemput/diantar = PENUTUP\n
      \n
      FALSE jika:\n
      - Ada pertanyaan (kapan? berapa? dimana? bisa?)\n
      - Ada permintaan (tolong, minta, bisa, bantu, dong) + object\n
      - Contoh FALSE: 'bisa dijemput?' (ini pertanyaan), 'tolong jemput' (ini permintaan)"
   ],

    'REMINDER' => [
        'patterns' => [
            '/^\s*(reminder|remind|ingatkan|ingat|pengingat)\s*$/i',
        ],
    ],

    'KAS_LAUNDRY' => [
        'patterns' => [
            '/^\s*(kas|saldo)\s*(l(a|o)u*ndr(y|i)|resto)\s*$/i',
        ],
    ],

    'CEK_TOKEN' => [
        'patterns' => [
            '/^(cek|lihat|info) (token|pln) (l(a|o)u*ndr(y|i)|resto)$/i',
        ],
    ],

    'BELI_TOKEN' => [
        'patterns' => [
            '/^(token|pln) (l(a|o)u*ndr(y|i)|resto) \d+$/i',
        ],
    ],

    'SALDO_IAK' => [
        'patterns' => [
            '/^(saldo|cek|info)\s*(iak)$/i',
        ],
    ],

    'SALDO_TOKOPAY' => [
        'patterns' => [
            '/^(saldo|cek|info)\s*(tokopay)$/i',
        ],
    ],
    
    'TARIK_TOKOPAY' => [
        'patterns' => [
            '/^(tarik|wd)\s*(tokopay) \d+$/i',
        ],
    ],  

    'SLIP_GAJI' => [
        'patterns' => [
            '/^(slip|gaji|slip gaji)$/i',
        ],
    ],

    'GAJI_CASH' => [
        'patterns' => [
            '/^(gaji (cash|kes))$/i',
        ],
    ],
];
