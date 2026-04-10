<?php 
return [  
    'PEMBUKA' => [
        'patterns' => [
            '/^\s*(p|ping|ka*k|ba*n*g|b*a*pa*k|i*bu*k*|a*de*k|he*a*l+o|as+a*l+a*mu*a*l+a*i*ku*m|tes)\s*$/i',
            // Sapaan pagi/sore/siang/malam + kak/bang - HANYA jika tidak diikuti pertanyaan/permintaan
            '/(pa*gi|so*re|si*a*ng|ma*la*m|ha*e*l+o+)\s*\b(ba*n*g|ka*k|pa*k|i*bu*k*|a*de*k*|a*na*k)\s*[,.]?\s*$/i',
            // Assalamualaikum - HANYA sapaan singkat. Jika ada "siap/udh/laundry" = STATUS, bukan PEMBUKA
            '/^\s*(assalamu|asalamu)[a-z]*\s*(wr\s*wb|kak|bang|pak|bu|mbak)?\s*$/i',
            '/^\s*.\s*$/i',
        ],
        'ai_prompt' => "User HANYA memberi sapaan awal singkat tanpa permintaan/isi pesan lain.\n
        Contoh: | halo | hai | ping | pagi | siang | malam | sore | kak | bang | pak | bu | assalamualaikum | assalamualaikum kak |\n
        PENTING: JIKA sapaan diikuti kalimat permintaan (misal: 'Bang, baju dulukan', 'Kak, jemput ya'), ini BUKAN PEMBUKA.\n
        PENTING: Jika sapaan + permintaan bill/tagihan (bisa kirimkan bill, kirim tagihan, minta bill) = TAGIHAN, bukan PEMBUKA.\n
        PENTING: Jika sapaan + permintaan nota/bon (minta nota, minta bon, kirim struk) = NOTA, bukan PEMBUKA.\n
        CRITICAL: Pesan mengandung tanda tanya (?) = bukan sekadar sapaan PEMBUKA. Contoh: 'Berarti sudah masuk kak?'\n
        CRITICAL - FALSE (BUKAN PEMBUKA): | kabari ya kak | kabarin ya | infokan ya kk | kasih kabar ya | = minta update/kabar (penutup/ack), BUKAN sapaan awal — pilih PENUTUP.\n
        CRITICAL - FALSE (BUKAN PEMBUKA): Balasan salam / jawaban atas salam = BUKAN pembuka percakapan. Contoh: | waalaikumsalam | walaikumsalam | wa alaikum salam | waalaikum salam | waalaikumussalam | = FALSE (bukan assalamualaikum)."
    ],

    'NOTA' => [
        'patterns' => [
            '/^\s*(bon|nota|struk)\s*$/i',
            // "minta nota", "pagi kak minta bon", "bisa kirim struk"
            '/\b(minta|tolong|bisa|boleh)\s*(nota|bon|struk)\s*(saya|ku|punya)?/i',
            '/\b(kirim|kirimkan)\s*(nota|bon|struk)\s*(saya|ku|punya)?/i',
            // Follow-up: nota/bon/belum masuk WhatsApp — "Laundry strika hari ini blm ada masuk wa nya"
            '/\b(laundry|loundry).{0,200}?\b(bl?m|belum|belom)\s+ada\s+masuk\s+(ke\s*)?(wa|whatsapp)\b/iu',
            '/\b(bl?m|belum|belom)\s+ada\s+masuk\s+(ke\s*)?(wa|whatsapp)\b.{0,120}?\b(laundry|loundry)\b/iu',
            // Singkat: "Wa nya blm masuk" / "wa blm masuk" (notifikasi nota belum masuk WA)
            '/\b(wa|whatsapp)\s+nya\s+(bl?m|belum|belom)\s+masuk\b/iu',
            '/\b(wa|whatsapp)\s+(bl?m|belum|belom)\s+masuk\b/iu',
            '/\b(bl?m|belum|belom)\s+masuk\b.{0,30}?\b(wa|whatsapp)\b/iu',
            // Sudah antar laundry tapi belum dapat notifikasi/nota di WA (bukan STATUS progres cuci)
            '/\b(antar|nyerahkan)\s+(laundry|loundry|londri|cucian)\b.{0,350}?\b(bl?m|belum|belom)\s+(dapat|dpt|ada|terima|kirim)\s+(notifikasi|notif|pemberitahuan)/iu',
            '/\b(bl?m|belum|belom)\s+(dapat|dpt|ada|terima)\s+(notifikasi|notif|pemberitahuan)\s*(via|lewat|pakai)?\s*(wa|whatsapp)\b.{0,160}?\b(antar|laundry|loundry|londri|cucian)\b/iu',
            '/\b(laundry|loundry|londri|cucian).{0,320}?\b(bl?m|belum|belom)\s+(dapat|dpt|ada|terima)\s+(notifikasi|notif|pemberitahuan)\s*(via|lewat|pakai)?\s*(wa|whatsapp)\b/iu',
            // Waktu (tadi sore/pagi, kemarin, hari Jumat…) + belum di WA = follow-up nota/notifikasi belum masuk WA (bukan tanya total uang)
            '/\b(yg|yang)\s+(td|tadi|kemarin|kmrn)\s*(pagi|siang|sore|malam)?\b.{0,200}?\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b/iu',
            '/\b(td|tadi|kemarin|kmrn)\s+(pagi|siang|sore|malam)\b.{0,200}?\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b/iu',
            '/\b(hari\s*)?(jumat|kamis|senin|selasa|rabu|sabtu|minggu)\b.{0,200}?\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b/iu',
            '/\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b.{0,60}?\b(brp|berapa)\b/iu',
            '/\b(brp|berapa)\b.{0,50}?\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b/iu',
        ],
        'ai_prompt' => "User meminta BON/NOTA/STRUK (dokumen bukti terima) sebagai fisik/cetak, ATAU menindaklanjuti karena bukti/nota belum masuk WhatsApp, seperti:\n
        | bon | nota | struk | bukti terima | minta bon | minta nota | minta struk |\n
        \n
        CRITICAL - TRUE (NOTA) - follow up bukti di WA:\n
        - Jika ada pola: (laundry/loundry) + (belum/blm/belom) + 'ada masuk' + (wa/whatsapp) = NOTA. Contoh: | Laundry strika hari ini blm ada masuk wa nya | laundry cuci belum ada masuk whatsapp | loundry saya blm ada masuk wa |\n
        - Kalimat singkat notifikasi WA belum masuk: | Wa nya blm masuk | wa blm masuk | blm masuk wa | = NOTA (follow-up bukti/nota di WA), BUKAN FALSE.\n
        - Sudah antar/nitip laundry ke outlet tapi belum dapat notifikasi/nota lewat WA = NOTA (follow-up bukti terima digital), BUKAN STATUS. Contoh: | Tadi malam saya antar londri tp sampai siang belum dapat notifikasi via wa | sudah antar laundry kemarin belum ada notif wa | belum dapat notifikasi wa padahal sudah antar cucian |\n
        - Waktu + belum di WA: user merujuk order/jam (tadi sore, tadi pagi, kemarin, hari Jumat, yg td sore, dll) lalu 'blm di wa' / 'belum di whatsapp' = follow-up NOTA/notifikasi digital belum masuk = NOTA. Contoh: | Kak yg td sore blm di wa ya brp nya | yang tadi pagi blm di wa | kemarin blm di wa kak | = NOTA. 'Brp nya' di sini = tanya notifikasi/nota (kok belum), BUKAN tanya total rupiah tagihan.\n
        - Ini komplain/follow-up nota struk bon belum dikirim ke WA customer = NOTA, BUKAN FALSE.\n
        \n
        FALSE (BUKAN NOTA) - PENTING:\n
        - User menanya 'berapa total?' / 'berapa biaya?' / 'total berapa?' / 'brapa total strika tadi?' murni tanya JUMLAH UANG (tanpa konteks blm di wa / nota belum masuk) = TAGIHAN, BUKAN NOTA.\n
        - Jika pesan jelas 'blm di wa' / 'belum di whatsapp' dengan konteks waktu (tadi sore, dll) = NOTA walaupun ada kata 'berapa' atau 'brp'."
    ],

    'REKENING' => [
        'patterns' => [
            '/\b(rekening|rek|norek|no\s*rek)\b/i',
            // QRIS = barcode pembayaran (customer sering bilang "barcode")
            '/\b(qris|qr\s*is|barcode|bar\s*code)\b/i',
            '/^\s*barcode\s+nya\s*(ka|kak|kk|bang|min|mbak|pak|bu)?\s*$/i',
            '/\b(transfer|tf|bayar)\s*(ke|ke\s*mana|kemana|dimana)\b/i',
            '/\b(ke\s*mana|kemana|dimana)\s*(transfer|bayar)\b/i',
        ],
        'ai_prompt' => "User menanyakan REKENING PEMBAYARAN atau meminta QRIS/BARCODE untuk pembayaran laundry (QRIS yang di-scan itu berupa barcode — anggap sama).\n
        TRUE (REKENING) - contoh:\n
        | rekening? | no rek? | nomor rekening? | minta rekening | rekening pembayaran? |\n
        | QRIS? | minta QRIS | QRIS pembayaran | link QRIS |\n
        | barcode? | minta barcode | barcode pembayaran | barcode nya kak | barcode nya ka | kirim barcode |\n
        | transfer ke mana? | bayar ke mana? | mau transfer ke mana? | nomor untuk transfer? |\n
        | BCA/BRI/BNI rekeningnya? | nomor rekening BCA? |\n
        PENTING: Kata 'barcode' dalam konteks bayar/transfer laundry = sama dengan minta QRIS = REKENING.\n
        \n
        FALSE (BUKAN REKENING):\n
        - User bertanya total tagihan (berapa total?) = TAGIHAN\n
        - User minta bon/nota = NOTA\n
        - CRITICAL: User memberitahu/pemberitahuan bahwa SUDAH transfer/mengirim = BUKAN REKENING, itu PENUTUP. Contoh: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim |"
    ],

    'TAGIHAN' => [
        'patterns' => [
            '/^\s*(bill|tagihan)\s*$/i',
            // Permintaan kirim bill/tagihan: "bisa kirimkan bill saya", "kirim tagihan", "minta bill"
            '/\b(kirim|kirimkan|minta|tolong)\s*(bill|tagihan)\s*(saya|ku|punya)?/i',
            '/\b(bisa|boleh)\s*(kirim|kirimkan)?\s*(bill|tagihan)/i',
            '/\b(bill|tagihan)\s*(saya|ku|punya)?\s*(kirim|kirimkan)?/i',
            // "laundry aku ada kk?", "laundry saya ada?" = tanya tagihan/bill punya saya (BUKAN "ada yang tinggal/luntur")
            '/\b(laundry|loundry)\s+(aku|saya|ku|punya\s+saya)\s+ada(?!\s+yang)\b/i',
            // "berapa kilo itu kak?", "brpa kilo kk?" = tanya berat order (kait tagihan/kg) — bukan daftar harga per item
            '/\b(brp|brpa|brapa|berapa)\s*kilo\b/i',
        ],
        'ai_prompt' => "User menanyakan TOTAL BIAYA/TAGIHAN laundry (jumlah uang yang harus dibayar) ATAU meminta dikirimkan bill/tagihan, seperti:\n
        | berapa total punya saya? | brapa total strika/cuci tadi? | totalnya berapa? | berapa tagihan? | berapa biaya laundry saya? |\n
        | total berapa kak? | brp total? | berapa total cuci? | berapa biayanya? |\n
        | bisa kirimkan bill saya | halo kak bisa kirimkan bill saya | kirim tagihan | minta bill | kirimkan tagihan saya |\n
        | laundry aku ada kk? | laundry saya ada? | laundry punya saya ada? | = tanya tagihan/bill punya saya = TAGIHAN\n
        | berapa kilo itu kak? | brpa kilo itu kk? | brp kilo? | = tanya berat cucian/order (hubungan ke tagihan) = TAGIHAN, BUKAN FALSE\n
        Jika user bertanya 'berapa' + (total/biaya/tagihan) atau (total/biaya) + 'berapa' = TAGIHAN\n
        Jika user minta/bisa kirimkan bill/tagihan = TAGIHAN (permintaan bill/tagihan)\n
        Jika user tanya 'laundry aku/saya ada?' = tanya tagihan punya saya = TAGIHAN\n
        \n
        CRITICAL - FALSE (BUKAN TAGIHAN) = NOTA:\n
        - Follow-up nota/notifikasi belum masuk WA: 'tadi sore blm di wa', 'yg td sore blm di wa ya brp nya', 'kemarin blm di whatsapp' = tanya bukti/notifikasi di WA = NOTA, BUKAN TAGIHAN (walaupun ada 'berapa'/'brp').\n
        \n
        CRITICAL - FALSE (BUKAN TAGIHAN) = HARGA:\n
        - User bertanya HARGA cuci/setrika PER ITEM (order belum tentu ada): | berapa 1 boneka kak? | berapa 2 baju? | boneka berapa? | brp harga boneka? |\n
        - Pola: 'berapa' + (angka) + nama barang laundry (boneka, baju, celana, handuk, selimut, jaket, sepatu, tas, karpet, sprei, dll) = tanya DAFTAR HARGA = HARGA, BUKAN tagihan order.\n
        - BUKAN FALSE: 'berapa kilo itu?' / 'brp kilo kak?' (tanya berat order) = TAGIHAN — bedakan dari 'berapa harga per kilo?' (= HARGA)."
    ],

   'PENUTUP' => [
      'patterns' => [
         // Ok + siap/siaaaap/kak — ack penutup (prioritas sebelum STATUS; bukan cek status laundry)
         '/^\s*\bok(?:e+)?\s+sia+p+\s*(kak|kk|bang|min|mbak|pak|bu|ya)?\s*\??\s*$/iu',
         '/^\s*\bok(?:e+)?\s+kak\s*\??\s*$/iu',
         // Ack tunggal: siap kak / siaaaap kk (bukan "udah siap kak?" / "kapan siap kak?" — itu STATUS)
         '/^\s*\bsia+p+\s*(kak|kk|bang|min|mbak|pak|bu|penya|punya|ya)?\s*\??\s*$/iu',
         // Konfirmasi pembayaran/transfer - HARUS sebelum REKENING dicek (via process exception)
         '/(telah berhasil mengirimkan|sudah transfer|sudah bayar|sudah kirim|sudah mengirim)\s*(ke\s*)?(rekening|rek)?/i',
         // Konfirmasi tagihan sudah lunas (penutup)
         '/^\s*(sudah|udah|udh|sdh)\s+lunas(\s+(ya\s*)?(kak|kk|bang|min|mbak|pak|bu))?\s*$/i',
         '/^\s*lunas(\s+ya)?(\s+(kak|kk|bang|min|mbak|pak|bu))?\s*$/i',
         // "kabari ya kak" / "infokan ya" = minta kabar (penutup); harus "… kabari ya" / "infokan ya" — bukan "infokan harga ya"
         '/\b(kabari|kabarin)\s+(ya|dong)\b/iu',
         '/\binfokan\s+(ya|dong)\b/iu',
         '/\bma*ka*(s|c)(i|e)*h\b/i',
         '/\bte*ri*ma*ka*si*h\b/i',
         '/\btha*nks\b/i',
         // Ok jangan match jika lanjut permintaan (bntu/bw/bantuin/dll)
         '/\b(thx|tq|ty|ok|gpp)\b(?!\s+(bntu|tolong|minta|bantu|bantuin|kirim|bw|bawa|antar|jemput|kasih|suruh|mohon))/i',
         '/\b(gak|ga)\s*apa\s*apa\b/i',
         // udah/sudah/dah - JANGAN match jika diikuti "siap", "selesai", atau "bisa diambil" (itu tanya status = STATUS)
         // JANGAN match jika diikuti angka (keluhan durasi: "ini udh 3hari ka", "udh 2 hari") — bukan penutup
         // la+h: "lah" hanya jika bukan kata pertama (bukan "Lah ya gmn" / tanya)
         // JANGAN match "sdh/udh" + saya/sy + pesan/order (bukan penutup); contoh: "Sdh sy psn" = sudah saya pesan
         // JANGAN match sudah/udah + janji/sepakat (janji/jadwal): "Kita sudah janji sore ini" — bukan penutup
         // hm/ok/sip harus kata utuh — jangan match "ok" di dalam "rok", "kaos", dll.
         // "udah/sudah/lah" sebagai penutup hanya jika kalimat pendek/ack singkat, bukan info panjang
         '/^\s*((hm+|\bok(?:e+)?\b|\bsip\b)\s*)*(y(a*)?\s*)?(u*da*h|s*u*da*h|la+h)(?!\s*\d)(?!\s*siap)(?!\s*selesai)(?!\s*bisa\s*(di\s*)?ambil)(?!\s+(sy|sya|saya|aku|gue|gw)\s+(psn|pesan|order|ordr|pesen))(?!\s+(janji|sepakat|kesepakatan|deal))(?:\s+(kak|kk|bang|min|mbak|pak|bu|ya))?\s*$/iu',
         '/(oh*)\s*(gi*tu+)/i',
         // ok + siap/siaaaap/sip (bukan kalimat panjang daftar pakaian)
         '/^\s*\b(ok(?:e+)?|oh)\b.{0,60}?\b(siap|sip|sia+p+)\b(?:\s+(kak|kk|bang|min|mbak|pak|bu|ya)?)?\s*$/iu',
         '/^reacted\s+[^\s]+$/i', // WhatsApp reactions: "Reacted ❤️", "Reacted 👍"
         // JANGAN match: nanti saya jemput, nanti saya ambil, akan saya antar, nanti saya antar, akan mengambil - itu BUKAN intent (user memberitahu akan ambil/antar sendiri)
      ],
      'ai_prompt' => "User memberikan PENUTUP/CLOSING/ACKNOWLEDGMENT tanpa pertanyaan atau permintaan lanjutan.\n
      TRUE jika:\n
      - Ucapan terima kasih: | terima kasih | makasih | thanks | thx |\n
      - Konfirmasi singkat: | ok deh | ok siap | ok siaaap | ok kak | ok siap kak | siap kak | iya lah kak | sudah | oke | baik |\n
      - Konfirmasi pembayaran/tagihan lunas: | lunas | lunas ya kak | lunas ya min | sudah lunas | udah lunas |\n
      - Konfirmasi status (non-bayar): | sudah diambil |\n
      - Konfirmasi transfer/pembayaran sudah dilakukan: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim | saya sudah transfer ke rekening kamu |\n
      - Konfirmasi jadwal (TANPA PERMINTAAN): | baik nanti dijemput | ok sore diantar | siap dijemput ya |\n
      - Minta kabar/update singkat (penutup): | kabari ya kak | kabarin ya min | infokan ya kk | kasih kabar ya |\n
      - Pemberitahuan jadwal: | nanti sore dijemput | besok diantar | jam 2 dijemput ya kak |\n
      - EMOJI/REACTION SAJA: | ❤️ | 👍 | 🙏 | 👌 | ✅ | atau kata 'Reacted' + emoji |\n
      - Kata kunci: baik/ok/iya/siap + (nanti/sore/besok/jam) + dijemput/diantar = PENUTUP\n
      \n
      FALSE (BUKAN PENUTUP) - CRITICAL:\n
      - Pemberitahuan user akan ambil/antar SENDIRI = BUKAN intent apapun: | akan menjemput | nanti saya jemput | nanti saya ambil | akan saya antar | nanti saya antar | akan mengambil | mau jemput | besok saya jemput | nanti saya antar |\n
      - Informasi \"belum diambil\" = status/info order (mis. | kak blm diambil loh | belum diambil kak |), bukan penutup.\n
      - Informasi \"sudah diantar/di anter\" oleh customer/suami/istri (mis. | laundry tadi pagi di anter sama suami saya |) = info proses, bukan penutup.\n
      - Kalimat info panjang yang kebetulan mengandung kata \"udah/sudah\" (mis. | loh kalo yg ini udah kaka ku, ini sblm lebaran |) = BUKAN PENUTUP.\n
      - KOMPLAIN/KELUHAN = BUKAN PENUTUP, biarkan CS manusia: | salah hitung | komplain | keluhan | ada salah | kurang bayar | kelebihan bayar | salah tagihan | salah total | salah jumlah |\n
      - PERMINTAAN/INSTRUKSI = BUKAN PENUTUP, biarkan CS manusia: | bisa sy ambil | letak aj dikursi | letakkan di | taruh di | tolong letak | minta letak | saya ambil | aku jemput |\n
      - Daftar/instruksi item laundry panjang (baju, celana, rok, kemeja, dll) dengan koma — bukan penutup percakapan = FALSE.\n
      \n
      FALSE jika:\n
      - CRITICAL: Pesan mengandung tanda tanya (?) atau kata tanya (dimana/kapan/berapa/gimana/dll) = bukan PENUTUP — KECUALI sekadar ack pendek saja: | siap kak? | ok siap? | ok siap kak? | (tanpa kata tanya lain seperti kapan/udah/dimana di pesan itu).\n
      - Contoh lain tanya: 'Berarti sudah masuk kak?' | 'Alhamdulillah foto dimana' (bisa tanpa ?)\n
      - Ada pertanyaan (kapan? berapa? dimana? bisa?)\n
      - Ada permintaan (tolong, minta, bisa, bantu, dong) + object\n
      - Pemberitahuan akan ambil/antar sendiri: 'nanti saya jemput', 'akan saya antar', 'nanti saya ambil', 'akan mengambil' = BUKAN PENUTUP\n
      - PERMINTAAN antar/jemput: 'kalau da kelar antar aja', 'klo selesai antar ya', 'udh kelar antar aja kak' = MINTA_JEMPUT_ANTAR (bukan PENUTUP)\n
      - Keluhan durasi/tunggu (bukan penutup): | ini udh 3hari ka | udah 2 hari belum | = BUKAN PENUTUP\n
      - Pertanyaan dengan partikel 'Lah' di awal: | Lah ya gmn | Lah kok gitu | = BUKAN PENUTUP\n
      - 'Ok' + permintaan (bntu/bw/bawa/kantong): | Ok bntu bw kantong | = PERMINTAAN, bukan PENUTUP\n
      - Pemberitahuan sudah pesan/order (bukan penutup): | Sdh sy psn | udah saya pesan | = BUKAN PENUTUP\n
      - Janji/kesepakatan waktu: | Kita sudah janji sore ini | udah sepakat jam 5 | = BUKAN PENUTUP\n
      - Contoh FALSE: 'bisa dijemput?' (pertanyaan), 'Berarti sudah masuk kak?' (pertanyaan), 'tolong jemput' (permintaan), 'nanti saya jemput' (bukan intent), 'Kak kalau da kelar antar aja kak' (permintaan antar = MINTA_JEMPUT_ANTAR)"
   ],

    'STATUS' => [
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
            '/\b(sudah|udah|udh|dah+|dh)\s*sia+p+\b/i',  // dh siap, udh siap, laundry saya udh siap?
            // siap+kak/kk/... = STATUS hanya jika BUKAN sekadar pesan pendek "siap kak" (= PENUTUP)
            '/(?!\A\s*\bsia+p+\s*(?:kak|kk|bang|min|mbak|pak|bu|penya|punya|ya)\s*\??\s*\z)\b(siap|sia+p+)\s*(kak|kk|bang|min|mbak|pak|bu|penya|punya)/iu',
            // tanya kapan/jam + siap (bukan ack "siap kak" saja)
            '/\b(kapan|kpn|jam\s*berapa|brp|berapa)\b.{0,40}?\b(siap|sia+p+|selesai|jadi)\b/iu',
            '/\b(sudah|udah|udh|dah|dh)\s*selesai/i',  // sudah selesai laundry nya kak? udh selesai kah?
            '/\b(udah|sudah|udh|sdh|dah|dh)\s*bisa\s*(di\s*)?ambil/i',  // udh bisa di ambil baju sy? sdh bisa diambil?
            '/\b(udah|sudah|udh|sdh|dah|dh)\s*bisa\s*(di\s*|d\s+)?jemput/i',  // udh/sdh bisa d jemput? (bukan minta kurir)
            // "yang pakaian harian apa bisa dijemput?" = tanya status (tanpa sdh/udh pun); beda dari minta kurir
            '/\b(pakaian|laundry|loundry|londri|cucian|strika|setrika|gosok|yang)\b.{0,120}?\bapa\b.{0,50}?\b(bisa|boleh)\s*(di)?(jemput|ambil)\b/iu',
            '/\bbisa\s*(di\s*)?ambil\s*\??/i',  // bisa diambil? / bisa di ambil?
            // "laundry saya udh siap" / "loundry saya udh siap" (typo)
            '/\b(laundry|loundry|la*u*ndr(y|i))\s+(saya|ku|sya)\s+(udh|udah|sudah|dah)\s*sia+p/i',
            '/atas\s+nama\s+.+\s*(udah|sudah)\s*\??/i',
            // "apakah sudh siap laundri saya?" — typo sudh/laundri; tanya siap + laundry/cucian
            '/\b(sudah|udah|sudh|udh|dah|dh)\s+siap\b.{0,120}?\b(laundry|loundry|laundri|londri|cucian)\b/iu',
            '/\b(apakah)\b.{0,120}?\bsiap\b.{0,120}?\b(laundry|loundry|laundri|londri|cucian)\b/iu',
        ],
        'ai_prompt' => "User menanyakan ATAU memberitahu status/progress laundry, seperti:\n
        Contoh kalimat tanya status (pilih intent STATUS, bukan kategori lain):\n
        | sudah selesai? | udh selesai kah? | bisa diambil? | kapan siap? | jam berapa siap? | sudah jadi? | jam berapa selesai? |\n
        | sudah bisa diambil? | udh bisa d jemput kak? | udh bisa di jemput? | pakaian harian apa sdh bisa dijemput? | yang strika sdh bisa dijemput? | kapan bisa diambil? | siapnya kapan? | siapnya jam berapa? |\n
        | atas nama IVAN udah? | atas nama X sudah? | laundry [nama] udah? | punya [nama] sudah? |\n
        \n
        KONFIRMASI/PEMBERITAHUAN status (sudah siap) — bedakan dari ack penutup:\n
        | sudah siap | udh siap | dah siap | dahh siapp | siapp kak | sudah jadi | ready |\n
        | laundry saya udh siap | loundry saya udh siap mbak | dh siap kk penya ku | siap kk penya | kak dahh siapp kak | dah siapp | sudah siap kak | udah siap kak? |\n
        (Hanya 'siap kak' / 'ok siap' / 'ok siap kak' tanpa konteks tanya status = intent PENUTUP, bukan STATUS.)\n
        \n
        PRIORITAS: 'apakah sudah/sudh/siap ... laundry/laundri/cucian saya?', 'sudh siap laundri?', 'udh siap cucian?' = tanya STATUS order (pilih STATUS).\n
        PENTING:\n
        - Pola 'yang/pakaian/laundry/... apa ... bisa dijemput/diambil?' (boleh tanpa sdh/udh) = tanya status order siap diambil = STATUS\n
        - Jika user bertanya 'kapan' atau 'jam berapa' + (siap/selesai/jadi/bisa diambil) = STATUS\n
        - Jika user bertanya 'atas nama [nama] udah/sudah?' = STATUS (tanya status laundry atas nama tertentu)\n
        - Jika user memberitahu/konfirmasi 'sudah siap' / 'dah siap' / 'siapp' / 'dahh siapp' / 'udah siap kak?' / 'kapan siap kak?' = STATUS (bukan sekadar 'siap kak' / 'ok siap' pendek = PENUTUP)\n
        \n
        FALSE (BUKAN STATUS) - CRITICAL:\n
        - Belum dapat notifikasi/nota via WA setelah sudah antar laundry = follow-up BON/NOTA digital = NOTA, BUKAN STATUS (bukan tanya sudah selesai/siap cuci).\n
        - 'Masih bisa siap gak?' / 'masih bisa siap?' + konteks mau bawa/antar laundry = user BELUM antar laundry, tanya AVAILABILITAS (masih terima/proses?) = JAM_OPERASIONAL. Contoh: | masih bisa siap gak kk mau bawa sprei | masih bisa siap mau antar |\n
        - 'tutup tgl berapa?' / 'tutup tanggal brp?' = tanya tanggal libur/tutup toko = JAM_OPERASIONAL (bukan STATUS)\n
        - atau yang menurut anda sangat yakin sebagai pertanyaan/pemberitahuan status laundry"
    ],

    'HARGA' => [
        'patterns' => [
            '/^\s*(harga|price)\s*$/i',
            // "berapa 1 boneka kak?" / "berapa 2 baju" = harga per item, bukan tagihan total
            '/\bberapa\s+(\d+\s+)?(biji|pcs|pc|buah|lembar\s+)?(boneka|baju|celana|handuk|selimut|jaket|sepatu|tas|karpet|sprei|bedcover|gorden|kemeja|rok|gaun|jas|hoodie|sweater|topi|sarung|mukena|jilbab|kerudung)\b/iu',
            // "berapa boneka?" tanpa angka
            '/\bberapa\s+(harga|biaya|cuci|setrika|strika|gosok)?\s*(boneka|baju|celana|handuk|selimut|jaket|sepatu|tas|karpet|sprei|bedcover|gorden|kemeja|rok|gaun|jas)\b/iu',
        ],
        'ai_prompt' => "User menanyakan harga/biaya laundry PER ITEM atau PER KILO, seperti:\n
        | berapa harga? | berapa biaya? | harga berapa? | biaya berapa? |\n
        | berapa harga baju? | berapa harga celana? | berapa harga handuk? | berapa harga boneka? | berapa harga sepatu? | berapa harga selimut? | berapa harga jaket? |\n
        | berapa 1 boneka kak? | berapa 2 baju? | boneka brp? | cuci boneka berapa? |\n
        | berapa harga per kilo? | berapa biaya per kg? | harga per kilo berapa? |\n
        PENTING: \n
        - 'berapa kilo itu?' / 'brp kilo kak?' TANPA kata harga/biaya = tanya berat order = TAGIHAN, BUKAN HARGA.\n
        - Jika user bertanya 'berapa' + (harga/biaya) + (item laundry atau per kilo) = HARGA\n
        - Jika user bertanya 'berapa' + angka + item (boneka, baju, dll) = HARGA (harga per item), BUKAN TAGIHAN total order\n
        - Jika user bertanya 'berapa' + (ongkir/ongkos/biaya antar/biaya jemput) = BUKAN CEK_HARGA, itu adalah MINTA_JEMPUT_ANTAR\n
        - Jika user bertanya 'berapa' + (berat saja tanpa item) = bisa NOTA/TAGIHAN, bedakan konteks\n
        \n
        CRITICAL - FALSE (BUKAN HARGA) - harga barang tambahan/ritel (bukan tarif cuci/setrika laundry):\n
        - Jika pesan menyebut parfum / plastik (kantong plastik) / pewangi / hanger / tissue / barang kemasan = BUKAN HARGA. Contoh: | berapa harga parfum kak? | berapa harga plastik? | sekarang berapa harga parfum? | harga kantong plastik? |\n
        - Intent khusus harga barang itu nanti terpisah; untuk sekarang jawab FALSE agar CS yang tangani.\n
        \n
        atau yang menurut anda sangat yakin sebagai pertanyaan harga/biaya laundry PER ITEM atau PER KILO (bukan barang tambahan di atas)"
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
        - Kalimat pernyataan/instruksi TANPA tanya harga + TANPA kata paket/member/langganan/deposit = BUKAN HARGA_PAKET.\n
        - Contoh FALSE: | Setrika aja | Denosa Setrika aja loh | Setrika aj | setrika aja kak | ntr biar kami strika aja | nanti biar kami setrika aja |\n
        - HARGA_PAKET HANYA jika user BERTANYA dengan kata paket/member/langganan, misal: paket setrika aja, paket bulanan setrika, paket member setrika.\n
        \n
        PENTING:\n
        - Jika user bertanya harga PER ITEM atau PER KILO (baju, celana, per kg) = HARGA (bukan HARGA_PAKET)\n
        \n
        CRITICAL - HARGA_PAKET (bukan MINTA_JEMPUT_ANTAR):\n
        - User bertanya HARGA PAKET/MEMBER/LANGGANAN/DEPOSIT sekaligus menyebut antar/jemput/ongkir/kurir/include antar = tetap HARGA_PAKET. Contoh: | berapa harga paket yang include antar jemput? | harga member pakai antar? | paket bulanan sama ongkir? | daftar harga paket + antar jemput |\n
        - Itu pertanyaan tarif paket varian include antar-jemput, BUKAN permintaan kurir jemput order sekarang."
    ],

    'PERMINTAAN' => [
        'case' => 3,
        'notify' => true,
        'patterns' => [
            '/(bi*sa*|bo*le*h).*(sa*ya*|a*ku|ka*mi).*(di)?(ambi*l|je*m*pu*t)/i',
            '/(bantu|bntu|tolong|minta|bisa)(?!.*(antar|jemput)).*(baju|pakaian|celana|handuk|boneka|sepatu|selimut|jaket|kantong)/i',
            // Satu jenis pakaian/item diambil/dulukan dulu dari order (bukan layanan kurir jemput ke alamat)
            '/\b(baju|pakaian|seragam|celana|jaket|kemeja|dress|rok|dinas)\b.{0,160}?\b(di\s*)?amb(i|l)\b.{0,40}?\b(dulu|dlu|duluan|dulukan)\b/iu',
            '/\b(di\s*)?amb(i|l)\b.{0,40}?\b(dulu|dlu|duluan).{0,120}?\b(baju|pakaian|seragam|celana|dinas)\b/iu',
            '/\b(didulukan|dulukan|prioritas|utamakan)\b.{0,80}?\b(baju|pakaian|seragam|celana)\b/iu',
        ],
        'ai_prompt' => "User melakukan PERMINTAAN KHUSUS atau INSTRUKSI KHUSUS terkait laundry.\n
        TRUE jika:\n
        - Permintaan treatment khusus: | bantu dibersihkan | tolong difokusin | baju ini dicuci khusus | noda ini dihilangkan |\n
        - Permintaan waktu/prioritas: | tolong dipercepat | didulukan ya | kapan bisa selesai | prioritas dong |\n
        - Permintaan cara treatment (untuk order yang sudah ada): | ganti parfum | jangan pakai pelembut | lipat rapi | setrika aja (instruksi untuk baju yang sudah di laundry) | ntr biar kami strika aja |\n
        - Konfirmasi ambil sendiri: | saya jemput nanti | aku ambil sendiri | nanti sore saya datang |\n
        - Ada kata: bantu/bntu (typo)/tolong/minta/bisa + object laundry (baju/celana/handuk/kantong/dll)\n
        - CRITICAL: Minta SALAH SATU pakaian/item tertentu diambil/dulukan lebih dulu dari order yang sudah ada (belum waktunya ambil semua): | bisa ga baju dinas diambil dulu? | tolong seragam coklat didulukan | kemarin ada laundry, baju X di ambil dulu | = PERMINTAAN, BUKAN MINTA_JEMPUT_ANTAR (bukan minta kurir jemput/antar dari alamat).\n
        \n
        FALSE jika (BUKAN PERMINTAAN - ini HARGA_PAKET):\n
        - User TANYA harga paket/member/langganan + spesifikasi layanan: | ada paket bulanan? setrika aja | paket setrika aja berapa? | ada paket cuci setrika? | paket bulanan cuci setrika? |\n
        - 'Setrika aja' / 'cuci setrika' setelah tanya paket = spesifikasi JENIS paket yang ditanya, bukan instruksi treatment = HARGA_PAKET\n
        \n
        FALSE jika:\n
        - Hanya sapaan: 'halo' tanpa permintaan\n
        - Hanya tanya status: 'kapan siap?' tanpa instruksi khusus\n
        - Minta kurir jemput/antar ke alamat (kamar/hotel/jemput laundry dari rumah): itu MINTA_JEMPUT_ANTAR\n
        - Pemberitahuan singkat: 'Mau jemput,jgn tutup dlu' (terlalu singkat/informal)"
    ],

   'MINTA_JEMPUT_ANTAR' => [
      'case' => 2,
      'notify' => true,
      'patterns' => [
         '/^\s*(je*m*pu*t|anta*r)\s*$/i',
         // "tolong/bantu/minta/bisa antar/jemput" (permintaan langsung). Catatan: "udh/sdh/sudah ... bisa dijemput" = STATUS (regex STATUS dicek lebih dulu + ada kata sudah/singkatannya).
         // "bisa/boleh" + jemput: permintaan layanan; pertanyaan status "X apa bisa dijemput" ada di regex STATUS (dicek lebih dulu)
         '/\b(tolong|minta|bantu|bntu|bisa|boleh)\s*(di)?(antar|jemput)\b/i',
         // Singkatan WA: "bs jmpt baju?", "bs jemput?" (= bisa jemput); tanpa "masih" = permintaan/tanya jemput (bukan JAM_OPERASIONAL)
         '/\b(bs|bis)\s*(jmpt|jemput|antar)\b/i',
         // "jam berapa bisa jemput?" = tanya jadwal jemput (bukan jam buka toko)
         '/\b(jam\s*)?(brp|brpa|berapa)\s*bisa\s*(jemput|antar)/i',
         // "jam brp bsk diantarnya?" / "jam berapa besok diantar?" = tanya jadwal pengantaran order
         '/\b(jam\s*)?(brp|brpa|berapa)\s*(bsk|besok|nanti)?\s*(di)?antar/i',
         '/\b(ka*pa*n|kpn)\s*(di)?(antar|jemput)/i',
         // Permintaan antar/jemput: "antar aja", "klo udh selesai antar aja", "kalau da kelar antar aja"
         '/\b(antar|jemput)\s*(aja|ya)(\s|$)/i',
         // "kalau udah selesai bantu antar ..." (ada kata bantu/tolong di tengah)
         '/(klo|kalau)\s*(da|udh|udah|sudah)?\s*(kelar|selesai)\s*(bantu|bntu|tolong)?\s*(antar|jemput)/i',
         '/(klo|kalau)\s*(udh|udah|sudah)?\s*selesai\s*(antar|jemput)/i',
         '/\b(selesai|kelar|udh|udah|sudah)\s+(antar|jemput)\s*(aja|ya)?/i',
         // "katanya mau antar", "katanya mau jemput" = relay/konfirmasi permintaan antar
         '/\bkatanya\s+mau\s*(antar|jemput)/i',
         // Ambil/jemput baju/laundry di kamar hotel/RS/sekolah/kost/alamat (minta kurir ke lokasi)
         '/\b(ambil|jemput)\b.{0,220}?\b(kamar|hotel|rumah\s*sakit|rs\b|sekolah|kost|apartemen|apart|gedung|alamat)\b/iu',
         '/\b(ambil|jemput)\b.{0,220}?\b(dpn|depan)\s+kamar\b/iu',
         '/\b(ambil|jemput)\b.{0,220}?\bkamar\s*\d+/iu',
         '/\b(ambil|jemput)\b.{0,220}?\b(jl\.|jalan|jl\s|rt\.|rw\.|gg\.|gang)\b/iu',
      ],
      'ai_prompt' => "User MEMINTA KURIR/LAUNDRY untuk datang JEMPUT atau ANTAR, ATAU menanyakan ONGKIR.\n
      TRUE (MINTA JEMPUT/ANTAR) - HARUS ADA kata permintaan atau pertanyaan atau instruksi jemput ke lokasi:\n
      - Kata kunci: tolong/minta/bisa/boleh/dong/kapan/berapa + jemput/antar\n
      - tolong jemput, minta dijemput, bisa diantar?, boleh dijemput?, kapan diantar?\n
      - jam brp bsk diantarnya?, jam berapa besok diantar?, kapan diantarnya? = tanya jadwal pengantaran order = MINTA_JEMPUT_ANTAR\n
      - bisa jemput kak?, nanti bisa jemput kak?, jemput dong, antar ya dong\n
      - Singkatan: bs jmpt baju?, bs jemput? = sama dengan bisa jemput = MINTA_JEMPUT_ANTAR\n
      - katanya mau antar, katanya mau jemput = relay/konfirmasi permintaan antar = MINTA_JEMPUT_ANTAR\n
      - brp ongkirnya?, berapa ongkosnya?, brp ong nya kak?, biaya antar?\n
      - CRITICAL: Instruksi ambil/jemput baju/laundry/kain/bedcover/sprei dari LOKASI (kamar hotel/kost, depan kamar, rumah sakit, sekolah, alamat/jalan) = MINTA_JEMPUT_ANTAR. Contoh: | kk ambil baju kotor sama bedcover di depan kamar 212 | ambil laundry di hotel | jemput di kamar 305 | ambil di RS |\n
      - CRITICAL - FALSE: Minta SATU jenis pakaian/item (baju dinas, seragam, dll.) diambil/dulukan DULU dari order/cucian yang sudah di laundry — prioritas item, BUKAN minta kurir jemput-antar = PERMINTAAN.\n
      \n
      FALSE (BUKAN MINTA JEMPUT/ANTAR) - SANGAT PENTING:\n
      - Tanya STATUS order (sudah/sdh/udh bisa dijemput/diambil) termasuk per kategori: | pakaian harian apa sdh bisa dijemput? | yang cuci setrika udh bisa dijemput? | = STATUS, BUKAN minta kurir.\n
      - User yang akan MENGAMBIL SENDIRI: | mau jemput | saya jemput | aku ambil | awak jemput | nanti saya jemput |\n
      - Konfirmasi/Pemberitahuan: | baik nanti dijemput | ok sore diantar | iya nanti akan dijemput | siap dijemput |\n
      - Hanya memberitahu jadwal tanpa permintaan: | nanti sore dijemput | besok diantar | jam 2 dijemput |\n
      - CRITICAL: 'masih bisa antar laundry?' / 'masih bisa antar?' = tanya AVAILABILITAS operasional = JAM_OPERASIONAL (bukan MINTA_JEMPUT_ANTAR). Kata 'masih' membedakan: tanya masih buka/bisa vs permintaan.\n
      - TRUE: 'jam berapa bisa jemput setrika?' / 'jam brp bisa jemput?' = user minta jemput kain setrikaan, tanya kapan kurir bisa jemput = MINTA_JEMPUT_ANTAR (bukan JAM_OPERASIONAL)\n
      - CRITICAL: 'Mau jemput' / 'saya jemput nanti' = User SENDIRI yang akan mengambil = FALSE (bedakan dari 'ambil baju di kamar X' = minta kurir)\n
      - CRITICAL: Instruksi 'ambil/jemput' + lokasi (kamar/hotel/RS/sekolah/alamat) = MINTA_JEMPUT_ANTAR walaupun TANPA kata tolong/minta/bisa — itu permintaan kurir ke alamat.\n
      - CRITICAL - FALSE: Pertanyaan HARGA PAKET/MEMBER/DEPOSIT + antar/jemput/ongkir (berapa harga paket include antar? harga member pakai antar?) = HARGA_PAKET, BUKAN MINTA_JEMPUT_ANTAR.\n
      - Contoh FALSE: 'Mau jemput,jgn tutup dlu' (user akan ambil sendiri, bukan minta kurir)\n
      - Contoh FALSE: 'Baikla nnti sore dijemput ya kak' (ini konfirmasi)\n
      - Contoh FALSE: 'Ok dijemput ya' (ini konfirmasi)\n
      - Contoh FALSE: 'Saya jemput nanti' (user akan ambil sendiri)"
   ],

    'JAM_OPERASIONAL' => [
        'patterns' => [
            '/(ka*pa*n|ma*si*h|masi|msih)\s*\b(bu*ka*|tu*tu*p)/i',
            // "Buka, buk?", "buka kak mau ambil baju" = tanya masih buka (sama seperti masih buka kak)
            '/\bbuka\s*[,.]?\s*(buk|bu|kak|bang|pak|mbak)\b/i',
            '/\bbuka\b.*\b(mau|ingin)\s*(ambil|jemput)\s*(baju|laundry)?/i',
            // "Ka londry buka ga??", "laundry buka gak kak?", "loundry buka atau tidak" = tanya apakah outlet masih buka
            '/\b(laundry|loundry|londri|londry|laondri|loundri)\b.{0,80}?\bbuka\b.{0,35}?\b(ga|gak|gk|nggak|enggak|tidak|tak|apa)\b/i',
            '/\bbuka\b.{0,30}?\b(ga|gak|gk)\b.{0,70}?\b(laundry|loundry|londri|londry|laondri|loundri)\b/i',
            '/\b(ma*si*h|masi|msih)\s+\bbuka\b.{0,25}?\b(ga|gak|gk|nggak)\b/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(bu*ka*|tu*tu*p)/i',
            // "tutup jam berapa kak?", "buka jam brp?" (urutan: tutup/buka dulu — pola di atas hanya "jam … berapa … tutup")
            '/\b(tu*tu*p|bu*ka*)\s+ja*m\s+(be*ra*pa|brp|brpa)/i',
            '/\bja*m\s+(be*ra*pa|brp|brpa)\s+(tu*tu*p|bu*ka*)\b/i',
            // "buka sampai kapan?", "tutup jam berapa?", "sampai jam berapa buka?"
            '/\b(bu*ka*|tu*tu*p)\s*(sa*mpa*i|sampe)\s*(ka*pa*n|ja*m\s*be*ra*pa*)?/i',
            '/\b(sa*mpa*i|sampe)\s*(ka*pa*n|ja*m\s*be*ra*pa*)\s*(bu*ka*|tu*tu*p)?/i',
            // "kak, bukak lagi kapan?", "kapan buka lagi?"
            '/\b(bukak|buka)\s+lagi\s*kapan\b/i',
            '/\bkapan\s+(bukak|buka)\s+lagi\b/i',
            // "sampai jam berapa laundry buka hari ini?", "sampai jam berapa loundry buka?" (laundry/loundry di tengah)
            '/\bsampai\s*(jam\s*)?(berapa|brp)\b.*\b(laundry|loundry)\s+buka/i',
            '/\bsampai\s*(jam\s*)?(berapa|brp)\b.*\bbuka\b/i',
            '/\b(ja*m)\s*(o*pe*ra*sio*na*l|bu*ka*|tu*tu*p)/i',
            // "kapan terakhir terima kain/laundry?", "terakhir terima jam berapa?"
            '/\b(ka*pa*n)\s*(te*ra*khir)\s*(te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|cu*ci)?/i',
            '/\b(te*ra*khir)\s*(te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|ja*m)?/i',
            // "kapan jadwal terakhir penerimaan?", "jadwal terakhir penerimaan" (konfirmasi ke petugas dulu)
            '/\b(kapan\s*)?(jadwal\s*)?(terakhir|batas)\s*(terima|penerimaan)/i',
            // "masih terima kain/baju/laundry/gosok?", "masih terima kak?" (masi/msih = typo masih)
            '/\b(ma*si*h|masi|msih)\s*(ne*ri*ma*|te*ri*ma*)\s*(ka*in|ba*ju|la*u*ndr(y|i)|cu*ci|go*so*k|se*tr*ika*|ka*k|ya)?\s*(a*ja*)?/i',
            // "masih/msh/masi/msih bisa?" atau "masih bisa terima kain?" (sistem jawab konfirmasi ke petugas dulu)
            '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis)\s*\??\s*$/i',
            '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis)\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?/i',
            // "masih nerima ga klo gosok aj?", "msih terima gosok aja?" (konfirmasi ke petugas)
            '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i',
            // "masih bisa antar laundry?", "masih bisa antar?", "masih bisa jemput?"
            '/\b(ma*si*h|masi|msih)\s*(bi*sa*)\s*(anta*r|je*m*pu*t)\s*(la*u*ndr(y|i)|cu*ci)?/i',
            // "liburnya kapan?", "kapan libur?", "hari libur?"
            '/\b(liburnya|libur)\s*(kapan|hari)?/i',
            '/\b(kapan)\s*(libur)/i',
            // "tutup tgl berapa?", "tutup tanggal brp?", "libur tgl berapa?"
            '/\b(tutup|libur)\s*(tgl|tanggal)\s*(brp|berapa)?/i',
            '/\b(tgl|tanggal)\s*(brp|berapa)\s*(tutup|libur)/i',
            // "masih bisa siap gak mau bawa sprei?", "masih bisa siap mau antar?"
            '/\b(ma*si*h|masi|msih)\s*(bi*sa*)\s*sia+p+\s*(gak|ga|g)?/i',
        ],
        'ai_prompt' => "User menanyakan jam operasional TOKO (buka/tutup) ATAU batas terima laundry ATAU jadwal libur.\n
        DUA JENIS (keduanya JAM_OPERASIONAL, sistem bedakan jawaban): (A) 'masih buka?' = jawab 'masih buka kak/bang'. (B) 'masih bisa?' / 'masih bisa terima kain?' / 'kapan jadwal terakhir penerimaan?' = jawab konfirmasi ke petugas dulu.\n
        | jam berapa buka? | jam berapa tutup? | kapan tutup? | masih buka? | masih bukak? | buka buk? | buka kak mau ambil baju? | sudah tutup? | jam operasional? | jam buka berapa? | sampai jam berapa laundry buka hari ini? | kak bukak lagi kapan? | kapan buka lagi? |\n
        | liburnya kapan? | kapan libur? | hari libur apa? | libur hari apa? | tutup tgl berapa? | tutup tanggal brp? | libur tgl berapa? |\n
        | besok pagi buka jam berapa? | besok buka jam brp ya? | nanti sore buka jam berapa? | untuk besok pagi buka jam brp ya? |\n
        | kapan terakhir terima kain? | kapan terakhir terima laundry? | terakhir terima jam berapa? | kapan jadwal terakhir penerimaan? | jadwal terakhir penerimaan? |\n
        | masih terima kain kak? | masih terima baju? | masih terima laundry? | masih terima kak? |\n
        | masih bisa antar laundry? | masih bisa antar? | masih bisa jemput? | = tanya AVAILABILITAS (masih buka/bisa layani) = JAM_OPERASIONAL\n
        | masih bisa siap gak kk mau bawa sprei? | masih bisa siap mau antar? | = user BELUM antar laundry, tanya apakah masih terima/proses = JAM_OPERASIONAL\n
        | Ka londry buka ga? | laundry buka gak? | loundry masih buka ga kak? | buka ga sekarang? (dengan konteks laundry/toko) = tanya apakah outlet MASIH BUKA = JAM_OPERASIONAL (BUKAN pertanyaan umum / bukan kategori lain)\n
        \n
        TRUE (JAM_OPERASIONAL) - PENTING:\n
        - JIKA user tanya apakah laundry/toko/outlet BUKA atau TIDAK / buka ga / buka gak / masih buka = JAM_OPERASIONAL (walaupun ada tanda ? atau typo londry/loundry).\n
        - JIKA ada kata BUKA/TUTUP/OPERASIONAL dalam konteks jam = JAM_OPERASIONAL\n
        - JIKA user tanya 'kapan terakhir terima' / 'terakhir terima jam berapa' = tanya batas waktu terima laundry = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih terima kain/baju/laundry' = tanya apakah masih buka terima = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih bisa antar/jemput' = tanya availabilitas operasional (masih buka layanan antar?) = JAM_OPERASIONAL. Kata 'masih' membedakan dari permintaan.\n
        - JIKA user tanya 'liburnya kapan?' / 'kapan libur?' / 'hari libur?' / 'tutup tgl berapa?' / 'tutup tanggal brp?' = tanya jadwal tutup/libur toko = JAM_OPERASIONAL\n
        - JIKA user tanya 'masih bisa siap?' + konteks mau bawa/antar laundry = user BELUM antar, tanya availabilitas (masih terima/proses?) = JAM_OPERASIONAL. Bukan STATUS (status = tanya order yang SUDAH ada).\n
        \n
        FALSE (BUKAN JAM_OPERASIONAL) - PENTING:\n
        - 'Jam berapa?' / 'Jam brp?' TANPA kata buka/tutup/operasional/terima sama sekali = user menanya WAKTU SAAT INI = FALSE\n
        - 'jam berapa bisa jemput?' / 'jam brp bisa jemput setrika?' = user minta jemput = MINTA_JEMPUT_ANTAR (bukan JAM_OPERASIONAL)\n
        - 'jam brp bsk diantarnya?' / 'jam berapa besok diantar?' / 'kapan diantarnya?' = tanya jadwal PENGANTARAN order = MINTA_JEMPUT_ANTAR (bukan JAM_OPERASIONAL)\n
        - 'bisa antar laundry?' / 'tolong antar laundry' / 'bs jmpt baju?' (TANPA 'masih') = permintaan jemput/antar = MINTA_JEMPUT_ANTAR\n
        - Contoh FALSE: 'jam berapa?', 'jam brp kak?' (tanpa buka/tutup) | 'jam berapa diantar?', 'kapan dijemput?', 'jam brp bsk diantarnya?'"
    ],

    'REMINDER' => [
        'patterns' => [
            '/^\s*(reminder|remind|ingatkan|ingat|pengingat)\s*$/i',
        ],
    ],

    'KARYAWAN' => [
        'patterns' => [
            '/^\s*(karyawan|crew|staf+|staff)\s+(.+)\s*$/i',  // staf+ = staf/staff
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
            '/^sli+p(\s+\d+)?$/i',  // slip/sliiip/... atau "slip 123" (i boleh berulang)
        ],
    ],

    'GAJI_CASH' => [
        'patterns' => [
            '/^gaji cash$/i',
        ],
    ],

    'GAJI_TF' => [
        'patterns' => [
            '/^gaji tf$/i',
        ],
    ],
];
