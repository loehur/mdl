<?php 
return [  
    'PEMBUKA' => [
        'patterns' => [
            '/^\s*(p|ping|ka*k|ba*n*g|b*a*pa*k|i*bu*k*|a*de*k|he*a*l+o|as+a*l+a*mu*a*l+a*i*ku*m|tes)\s*$/i',
            // Sapaan pagi/sore/siang/malam + kak/bang - HANYA jika tidak diikuti pertanyaan/permintaan
            '/(pa*gi|so*re|si*a*ng|ma*la*m|ha*e*l+o+)\s*\b(ba*n*g|ka*k|pa*k|i*bu*k*|a*de*k*|a*na*k)\s*[,.]?\s*$/i',
            // Assalamualaikum - HANYA jika tidak diikuti pertanyaan/permintaan (misal: "assalamualaikum, kain dah siap?" -> STATUS)
            '/^\s*(assalamu|asalamu)[^,]*\s*$/i',
            '/^\s*.\s*$/i',
        ],
        'ai_prompt' => "User HANYA memberi sapaan awal singkat tanpa permintaan/isi pesan lain.\n
        Contoh: | halo | hai | ping | pagi | siang | malam | sore | kak | bang | pak | bu | assalamualaikum | assalamualaikum kak |\n
        PENTING: JIKA sapaan diikuti kalimat permintaan (misal: 'Bang, baju dulukan', 'Kak, jemput ya'), ini BUKAN PEMBUKA."
    ],

    'NOTA' => [
        'patterns' => [
            '/^\s*(bon|nota)\s*$/i',
        ],
        'ai_prompt' => "User meminta BON/NOTA/STRUK (dokumen bukti terima) sebagai fisik/cetak, seperti:\n
        | bon | nota | struk | bukti terima | minta bon | minta nota | minta struk |\n
        \n
        FALSE (BUKAN NOTA) - PENTING:\n
        - User menanya 'berapa total?' / 'berapa biaya?' / 'total berapa?' / 'brapa total strika tadi?' = itu TAGIHAN (tanya jumlah uang), BUKAN permintaan bon/nota = FALSE"
    ],

    'REKENING' => [
        'patterns' => [
            '/\b(rekening|rek|norek|no\s*rek)\b/i',
            '/\b(qris|qr\s*is)\b/i',
            '/\b(transfer|tf|bayar)\s*(ke|ke\s*mana|kemana|dimana)\b/i',
            '/\b(ke\s*mana|kemana|dimana)\s*(transfer|bayar)\b/i',
        ],
        'ai_prompt' => "User menanyakan REKENING PEMBAYARAN atau meminta QRIS untuk pembayaran laundry.\n
        TRUE (REKENING) - contoh:\n
        | rekening? | no rek? | nomor rekening? | minta rekening | rekening pembayaran? |\n
        | QRIS? | minta QRIS | QRIS pembayaran | link QRIS |\n
        | transfer ke mana? | bayar ke mana? | mau transfer ke mana? | nomor untuk transfer? |\n
        | BCA/BRI/BNI rekeningnya? | nomor rekening BCA? |\n
        \n
        FALSE (BUKAN REKENING):\n
        - User bertanya total tagihan (berapa total?) = TAGIHAN\n
        - User minta bon/nota = NOTA\n
        - CRITICAL: User memberitahu/pemberitahuan bahwa SUDAH transfer/mengirim = BUKAN REKENING, itu PENUTUP. Contoh: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim |"
    ],

    'TAGIHAN' => [
        'patterns' => [
            '/^\s*(bill|tagihan)\s*$/i',
        ],
        'ai_prompt' => "User menanyakan TOTAL BIAYA/TAGIHAN laundry (jumlah uang yang harus dibayar), seperti:\n
        | berapa total punya saya? | brapa total strika/cuci tadi? | totalnya berapa? | berapa tagihan? | berapa biaya laundry saya? |\n
        | total berapa kak? | brp total? | berapa total cuci? | berapa biayanya? |\n
        Jika user bertanya 'berapa' + (total/biaya/tagihan) atau (total/biaya) + 'berapa' = TAGIHAN"
    ],

    'STATUS' => [
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
            '/\b(sudah|dah+|dh)\s*sia+p+\b/i',  // dh = singkatan dah/sudah (kain ku dh siap?)
            '/\bsia+p+\s*(kak|bang|pak|bu|ya)?\s*$/i',
            '/atas\s+nama\s+.+\s*(udah|sudah)\s*\??/i',
        ],
        'ai_prompt' => "User menanyakan ATAU memberitahu status/progress laundry, seperti:\n
        PERTANYAAN status:\n
        | sudah selesai? | bisa diambil? | kapan siap? | jam berapa siap? | sudah jadi? | jam berapa selesai? |\n
        | sudah bisa diambil? | kapan bisa diambil? | siapnya kapan? | siapnya jam berapa? |\n
        | atas nama IVAN udah? | atas nama X sudah? | laundry [nama] udah? | punya [nama] sudah? |\n
        \n
        KONFIRMASI/PEMBERITAHUAN status (sudah siap):\n
        | sudah siap | dah siap | dahh siapp | siapp kak | siap kak | sudah jadi | ready |\n
        | kak dahh siapp kak | dah siapp | sudah siap kak |\n
        \n
        PENTING:\n
        - Jika user bertanya 'kapan' atau 'jam berapa' + (siap/selesai/jadi/bisa diambil) = STATUS\n
        - Jika user bertanya 'atas nama [nama] udah/sudah?' = STATUS (tanya status laundry atas nama tertentu)\n
        - Jika user memberitahu/konfirmasi 'sudah siap' / 'dah siap' / 'siapp' / 'dahh siapp' = STATUS\n
        \n
        FALSE (BUKAN STATUS) - CRITICAL:\n
        - 'Masih bisa siap gak?' / 'masih bisa siap?' + konteks mau bawa/antar laundry = user BELUM antar laundry, tanya AVAILABILITAS (masih terima/proses?) = JAM_OPERASIONAL. Contoh: | masih bisa siap gak kk mau bawa sprei | masih bisa siap mau antar |\n
        - atau yang menurut anda sangat yakin sebagai pertanyaan/pemberitahuan status laundry"
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

    'HARGA_PAKET' => [
        'patterns' => [
            // TODO: tambah pattern sesuai kebutuhan
        ],
        'ai_prompt' => "User menanyakan HARGA PAKET, HARGA MEMBER, HARGA PAKET BULANAN, HARGA DEPOSIT MEMBER, atau HARGA DEPOSIT PAKET laundry.\n
        TRUE (HARGA_PAKET) - HARUS ADA kata paket/member/langganan/deposit:\n
        | berapa harga paket? | harga paket berapa? | paket laundry berapa? | daftar harga paket? |\n
        | berapa harga member? | harga member berapa? | biaya jadi member? |\n
        | berapa harga paket bulanan? | paket bulanan berapa? | langganan bulanan berapa? | harga langganan? |\n
        | paket cuci bulanan? | member paket berapa? |\n
        | berapa harga deposit member? | harga deposit member berapa? | deposit member berapa? |\n
        | berapa harga deposit paket? | harga deposit paket berapa? | deposit paket berapa? |\n
        | paket setrika aja berapa? | paket bulanan setrika? | paket member setrika? | ada paket bulanan? setrika aja | paket cuci setrika berapa? |\n
        \n
        CRITICAL - FALSE (BUKAN HARGA_PAKET):\n
        - 'Setrika aja' / 'Setrika aj' / 'Setrika saja' TANPA kata paket/member/langganan = BUKAN HARGA_PAKET. Ini bisa instruksi treatment atau pernyataan lain.\n
        - Contoh FALSE: | Setrika aja | Denosa Setrika aja loh | Setrika aj | setrika aja kak | = BUKAN tanya paket.\n
        - HARGA_PAKET HANYA jika user BERTANYA dengan kata paket/member/langganan, misal: paket setrika aja, paket bulanan setrika, paket member setrika.\n
        \n
        PENTING:\n
        - Jika user bertanya harga PER ITEM atau PER KILO (baju, celana, per kg) = HARGA (bukan HARGA_PAKET)"
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
      - CRITICAL: 'masih bisa antar laundry?' / 'masih bisa antar?' = tanya AVAILABILITAS operasional = JAM_OPERASIONAL (bukan MINTA_JEMPUT_ANTAR). Kata 'masih' membedakan: tanya masih buka/bisa vs permintaan.\n
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
        - Permintaan cara treatment (untuk order yang sudah ada): | ganti parfum | jangan pakai pelembut | lipat rapi | setrika aja (instruksi untuk baju yang sudah di laundry) |\n
        - Konfirmasi ambil sendiri: | saya jemput nanti | aku ambil sendiri | nanti sore saya datang |\n
        - Ada kata: bantu/tolong/minta/bisa + object laundry (baju/celana/handuk/dll)\n
        \n
        FALSE jika (BUKAN PERMINTAAN - ini HARGA_PAKET):\n
        - User TANYA harga paket/member/langganan + spesifikasi layanan: | ada paket bulanan? setrika aja | paket setrika aja berapa? | ada paket cuci setrika? | paket bulanan cuci setrika? |\n
        - 'Setrika aja' / 'cuci setrika' setelah tanya paket = spesifikasi JENIS paket yang ditanya, bukan instruksi treatment = HARGA_PAKET\n
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
            // "buka sampai kapan?", "tutup jam berapa?", "sampai jam berapa buka?"
            '/\b(bu*ka*|tu*tu*p)\s*(sa*mpa*i|sampe)\s*(ka*pa*n|ja*m\s*be*ra*pa*)?/i',
            '/\b(sa*mpa*i|sampe)\s*(ka*pa*n|ja*m\s*be*ra*pa*)\s*(bu*ka*|tu*tu*p)?/i',
            '/\b(ja*m)\s*(o*pe*ra*sio*na*l|bu*ka*|tu*tu*p)/i',
            // "kapan terakhir terima kain/laundry?", "terakhir terima jam berapa?"
            '/\b(ka*pa*n)\s*(te*ra*khir)\s*(te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|cu*ci)?/i',
            '/\b(te*ra*khir)\s*(te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|ja*m)?/i',
            // "masih terima kain/baju/laundry?", "masih terima kak?"
            '/\b(ma*si*h)\s*(te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|cu*ci|ka*k|ya)?/i',
            // "masih bisa antar laundry?", "masih bisa antar?", "masih bisa jemput?"
            '/\b(ma*si*h)\s*(bi*sa*)\s*(anta*r|je*m*pu*t)\s*(la*u*ndr(y|i)|cu*ci)?/i',
            // "liburnya kapan?", "kapan libur?", "hari libur?"
            '/\b(liburnya|libur)\s*(kapan|hari)?/i',
            '/\b(kapan)\s*(libur)/i',
            // "masih bisa siap gak mau bawa sprei?", "masih bisa siap mau antar?"
            '/\b(ma*si*h)\s*(bi*sa*)\s*sia+p+\s*(gak|ga|g)?/i',
        ],
        'ai_prompt' => "User menanyakan jam operasional TOKO (buka/tutup) ATAU batas terima laundry ATAU jadwal libur, seperti:\n
        | jam berapa buka? | jam berapa tutup? | kapan tutup? | masih buka? | sudah tutup? | jam operasional? | jam buka berapa? |\n
        | liburnya kapan? | kapan libur? | hari libur apa? | libur hari apa? |\n
        | besok pagi buka jam berapa? | besok buka jam brp ya? | nanti sore buka jam berapa? | untuk besok pagi buka jam brp ya? |\n
        | kapan terakhir terima kain? | kapan terakhir terima laundry? | terakhir terima jam berapa? |\n
        | masih terima kain kak? | masih terima baju? | masih terima laundry? | masih terima kak? |\n
        | masih bisa antar laundry? | masih bisa antar? | masih bisa jemput? | = tanya AVAILABILITAS (masih buka/bisa layani) = JAM_OPERASIONAL\n
        | masih bisa siap gak kk mau bawa sprei? | masih bisa siap mau antar? | = user BELUM antar laundry, tanya apakah masih terima/proses = JAM_OPERASIONAL\n
        \n
        TRUE (JAM_OPERASIONAL) - PENTING:\n
        - JIKA ada kata BUKA/TUTUP/OPERASIONAL dalam konteks jam = JAM_OPERASIONAL\n
        - JIKA user tanya 'kapan terakhir terima' / 'terakhir terima jam berapa' = tanya batas waktu terima laundry = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih terima kain/baju/laundry' = tanya apakah masih buka terima = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih bisa antar/jemput' = tanya availabilitas operasional (masih buka layanan antar?) = JAM_OPERASIONAL. Kata 'masih' membedakan dari permintaan.\n
        - JIKA user tanya 'liburnya kapan?' / 'kapan libur?' / 'hari libur?' = tanya jadwal tutup/libur toko = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih bisa siap?' + konteks mau bawa/antar laundry = user BELUM antar, tanya availabilitas (masih terima/proses?) = JAM_OPERASIONAL. Bukan STATUS (status = tanya order yang SUDAH ada).\n
        \n
        FALSE (BUKAN JAM_OPERASIONAL) - PENTING:\n
        - 'Jam berapa?' / 'Jam brp?' TANPA kata buka/tutup/operasional/terima sama sekali = user menanya WAKTU SAAT INI = FALSE\n
        - 'bisa antar laundry?' / 'tolong antar laundry' (TANPA 'masih') = permintaan jemput/antar = MINTA_JEMPUT_ANTAR\n
        - Contoh FALSE: 'jam berapa?', 'jam brp kak?' (tanpa buka/tutup) | 'jam berapa diantar?', 'kapan dijemput?'"
    ],

   'PENUTUP' => [
      'patterns' => [
         // Konfirmasi pembayaran/transfer - HARUS sebelum REKENING dicek (via process exception)
         '/(telah berhasil mengirimkan|sudah transfer|sudah bayar|sudah kirim|sudah mengirim)\s*(ke\s*)?(rekening|rek)?/i',
         '/\bma*ka*(s|c)(i|e)*h\b/i',
         '/\bte*ri*ma*ka*si*h\b/i',
         '/\btha*nks\b/i',
         '/\b(thx|tq|ty|ok)\b/i',
         // udah/sudah/dah - JANGAN match jika diikuti "siap" (itu tanya status = STATUS)
         '/((hm+|ok(e*)?|sip)\s*)*(y(a*)?\s*)?(u*da*h|s*u*da*h|la+h)(?!\s*siap)/i',
         '/(oh*)\s*(gi*tu+)/i',
         '/(ok|oh).*(siap|sip|ok)/i',
         '/^reacted\s+[^\s]+$/i', // WhatsApp reactions: "Reacted ❤️", "Reacted 👍"
         // Pemberitahuan akan jemput/antar: "nanti saya jemput", "aku ambil nanti", "akan diantar"
         '/(saya|aku|awak)\s+(jemput|ambil)\s*(nanti|ya|kak)?/i',
         '/(nanti|besok|akan)\s+(saya|aku)?\s*(jemput|antar|diantar|dijemput)/i',
      ],
      'ai_prompt' => "User memberikan PENUTUP/CLOSING/ACKNOWLEDGMENT tanpa pertanyaan atau permintaan lanjutan.\n
      TRUE jika:\n
      - Ucapan terima kasih: | terima kasih | makasih | thanks | thx |\n
      - Konfirmasi singkat: | ok deh | siap kak | iya lah kak | sudah | oke | baik |\n
      - Konfirmasi status: | sudah lunas | sudah diambil |\n
      - Konfirmasi transfer/pembayaran sudah dilakukan: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim | saya sudah transfer ke rekening kamu |\n
      - Konfirmasi jadwal (TANPA PERMINTAAN): | baik nanti dijemput | ok sore diantar | iya nanti saya jemput | siap dijemput ya |\n
      - Pemberitahuan jadwal: | nanti sore dijemput | besok diantar | jam 2 dijemput ya kak |\n
      - Pemberitahuan akan jemput/antar laundry: | nanti saya jemput | aku ambil nanti | akan saya antar | mau jemput | besok saya jemput | nanti diantar | (user MEMBERITAHU akan jemput/antar, bukan permintaan) = PENUTUP\n
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
            '/^slip(\s+\d+)?$/i',  // "slip" atau "slip 123" (spasi + angka berapa digit)
        ],
    ],

    'GAJI_CASH' => [
        'patterns' => [
            '/^gaji cash$/i',
        ],
    ],
];
