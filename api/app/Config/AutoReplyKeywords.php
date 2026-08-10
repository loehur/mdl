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
        CRITICAL - FALSE (BUKAN PEMBUKA): | kabari ya kak | kabarin ya | infokan ya kk | kasih kabar ya | = minta update/kabar, BUKAN sapaan awal — pilih FALSE (bukan PENUTUP, biarkan CS).\n
        CRITICAL - FALSE (BUKAN PEMBUKA): Balasan salam / jawaban atas salam = BUKAN pembuka percakapan. Contoh: | waalaikumsalam | walaikumsalam | wa alaikum salam | waalaikum salam | waalaikumussalam | = FALSE (bukan assalamualaikum)."
    ],

    'NOTA' => [
        'patterns' => [
            '/^\s*(bon|nota|struk)\s*$/i',
            // "cek bon", "cek nota", "cek struk"
            '/^\s*(cek|cekin|cekkan)\s*(bon|nota|struk)\s*$/i',
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
            // Minta info nota/order setelah antar: "Tolong infonya. Laondri yang saya antar pagi tadi" (bukan tanya siap cucian)
            '/\b(info|infonya|informasi)\b.{0,280}?\b(laundry|loundry|londri|laondri|cucian)\b.{0,220}?\b(antar|nyerahkan|nitip)\b.{0,120}?\b(pagi|siang|sore|malam)\b.{0,40}?\b(tadi|td|kemarin|kmrn)\b/iu',
            // Memberitahu pilihan layanan untuk cucian yang baru diantar/masukkan: "yg saya masukkan tadi, cuci setrika reguler ya"
            // Harus ada layanan nyata — kata laundry/loundry/item saja BUKAN cukup untuk NOTA
            '/\b(yg|yang)\s+(saya|aku|kami)\s+(masukkan|antar|anter|serahkan|titip|bawa|nyerahkan|nitip)\s+(tadi|kemarin|kmrn|td)\b.{0,280}?\b(cuci\s*setrika|cuci\s*strika|cuci\s*aja|setrika\s*aja|strika\s*aja|setrika|strika|reguler|regular|ekspres|ekspress|express|kilat|manual|biasa)\b/iu',
            '/\b(yg|yang)\s+(saya|aku|kami)\s+(masukkan|antar|anter|serahkan|titip|bawa|nyerahkan|nitip)\b.{0,200}?\b(cuci\s*setrika|cuci\s*strika|cuci\s*aja|setrika\s*aja|strika\s*aja|setrika|strika|reguler|regular|ekspres|ekspress|express|kilat|manual|biasa)\b/iu',
            '/\b(kain|pakaian|cucian|baju|item)\s+(yang\s+)?(saya|aku|kami)\s+(antar|anter|masukkan|titip|bawa|nyerahkan|nitip)\b.{0,120}?\b(tadi|kemarin|kmrn|td)?\s*(pagi|siang|sore|malam)?\b.{0,200}?\b(cuci\s*setrika|cuci\s*strika|cuci\s*aja|setrika\s*aja|strika\s*aja|setrika|strika|reguler|regular|ekspres|express|kilat|manual|biasa)\b/iu',
            '/\b(saya|aku|kami)\s+(antar|anter|masukkan|titip|bawa|nyerahkan|nitip)\b.{0,80}?\b(tadi|kemarin|kmrn|td|pagi|siang|sore|malam|hari\s+ini)\b.{0,250}?\b(cuci\s*setrika|cuci\s*strika|cuci\s*aja|setrika\s*aja|strika\s*aja|setrika|strika|reguler|regular|ekspres|express|kilat)\b/iu',
            '/\b(reguler|regular|ekspres|ekspress|express|kilat|cuci\s*setrika|cuci\s*strika|setrika\s*aja|strika\s*aja)\b.{0,200}?\b(yg|yang)\s+(saya|aku|kami)\s+(masukkan|antar|anter|titip|bawa|nyerahkan|nitip)\b/iu',
            '/\b(pilih|itu)\b.{0,50}?\b(ekspres|ekspress|express|reguler|regular|kilat|setrika|strika|cuci)\b.{0,180}?\b(antar|masukkan|titip|bawa|nyerahkan|nitip)\b/iu',
            '/\b(antar|masukkan|titip|bawa|nyerahkan|nitip)\b.{0,180}?\b(pilih|itu)\b.{0,50}?\b(ekspres|express|reguler|regular|kilat|setrika|strika|cuci)\b/iu',
        ],
        'ai_prompt' => "User meminta BON/NOTA/STRUK (dokumen bukti terima) sebagai fisik/cetak, ATAU menindaklanjuti karena bukti/nota belum masuk WhatsApp, seperti:\n
        | bon | nota | struk | bukti terima | minta bon | minta nota | minta struk |\n
        \n
        CRITICAL - TRUE (NOTA) - follow up bukti di WA:\n
        - Jika ada pola: (laundry/loundry) + (belum/blm/belom) + 'ada masuk' + (wa/whatsapp) = NOTA. Contoh: | Laundry strika hari ini blm ada masuk wa nya | laundry cuci belum ada masuk whatsapp | loundry saya blm ada masuk wa |\n
        - Kalimat singkat notifikasi WA belum masuk: | Wa nya blm masuk | wa blm masuk | blm masuk wa | = NOTA (follow-up bukti/nota di WA), BUKAN FALSE.\n
        - Sudah antar/nitip laundry ke outlet tapi belum dapat notifikasi/nota lewat WA = NOTA (follow-up bukti terima digital), BUKAN STATUS. Contoh: | Tadi malam saya antar londri tp sampai siang belum dapat notifikasi via wa | sudah antar laundry kemarin belum ada notif wa | belum dapat notifikasi wa padahal sudah antar cucian |\n
        - Waktu + belum di WA: user merujuk order/jam (tadi sore, tadi pagi, kemarin, hari Jumat, yg td sore, dll) lalu 'blm di wa' / 'belum di whatsapp' = follow-up NOTA/notifikasi digital belum masuk = NOTA. Contoh: | Kak yg td sore blm di wa ya brp nya | yang tadi pagi blm di wa | kemarin blm di wa kak | = NOTA. 'Brp nya' di sini = tanya notifikasi/nota (kok belum), BUKAN tanya total rupiah tagihan.\n
        - CRITICAL - TRUE (NOTA): Pola minta INFO + (laondri/laundry) + yang saya antar + pagi/siang/sore/malam + tadi/kemarin = follow-up NOTA/bukti order setelah antar (bukan sekadar sapaan). Contoh: | Tolong infonya. Laondri yang saya antar pagi tadi | infonya laundry yang saya antar siang tadi kak | = NOTA, BUKAN FALSE.\n
        - CRITICAL - TRUE (NOTA): User merujuk cucian yang BARU/KEMARIN diantar/masukkan/diserahkan + sekaligus menjelaskan PILIHAN LAYANAN (cuci setrika, setrika aja, reguler, ekspres, manual, biasa, dll) = konfirmasi detail order untuk nota/bukti terima = NOTA. HARUS ada pilihan layanan, BUKAN sekadar daftar item. Contoh: | Kak, yg saya masukkan tadi, cuci setrika manual n cuci setrika biasa, reguler yaaa | kain yang saya antar tadi malam, itu setrika aja ya | yang saya antar pagi ini cuci setrika ya kak | pilih yang ekspres untuk laundry yang saya titip tadi | cuci setrika ya kak untuk yang saya bawa tadi siang | = NOTA.\n
        - Pola rujukan: (yang/yg) (saya/aku) (masukkan/antar/titip/bawa) + (tadi/kemarin/pagi/siang/sore/malam) ATAU (kain/pakaian/cucian) (yang) (saya) (antar/masukkan) + waktu.\n
        - Pola layanan (WAJIB untuk TRUE di atas): cuci setrika, setrika aja, reguler/regular, ekspres/express, kilat, manual, biasa, pilih yang ekspres, itu setrika aja. Kata laundry/loundry/item saja BUKAN pilihan layanan.\n
        - Ini komplain/follow-up nota struk bon belum dikirim ke WA customer = NOTA, BUKAN FALSE.\n
        \n
        FALSE (BUKAN NOTA) - PENTING:\n
        - CRITICAL - FALSE: Hanya memberitahu ada laundry kemarin/hari ini + daftar ITEM (celana putih, baju, dll) TANPA minta bon/nota/struk DAN TANPA sebut pilihan layanan (cuci setrika/reguler/ekspres/dll) = FALSE. Contoh: | saya kn kmrin ada loundry dlm nya ada clna putih | kemarin ada laundry ada celana putih | = FALSE, BUKAN NOTA.\n
        - User menanya 'berapa total?' / 'berapa biaya?' / 'total berapa?' / 'brapa total strika tadi?' murni tanya JUMLAH UANG (tanpa konteks blm di wa / nota belum masuk) = TAGIHAN, BUKAN NOTA.\n
        - Jika pesan jelas 'blm di wa' / 'belum di whatsapp' dengan konteks waktu (tadi sore, dll) = NOTA walaupun ada kata 'berapa' atau 'brp'."
    ],

    'REKENING' => [
        'patterns' => [
            '/\b(rekening|rek|norek|no\s*rek)\b/i',
            // QRIS = barcode pembayaran (customer sering bilang "barcode")
            '/\b(qris|qr\s*is|barcode|bar\s*code)\b/i',
            '/^\s*barcode\s+nya\s*(ka|kak|kk|bang|min|mbak|pak|bu)?\s*$/i',
            // Singkat: minta nomor/izin transfer tanpa "ke mana" (mis. "bisa tf kak", "mau transfer kak")
            '/\b(bisa|boleh|mau|minta)\s+(tf|transfer)\b/i',
            '/\b(minta|kirim|share)\s+(no|nomor|norek|rek)\b/i',
            '/\b(transfer|tf|bayar)\s*(ke|ke\s*mana|kemana|dimana)\b/i',
            '/\b(ke\s*mana|kemana|dimana)\s*(transfer|bayar)\b/i',
        ],
        'ai_prompt' => "User menanyakan REKENING PEMBAYARAN atau meminta QRIS/BARCODE untuk pembayaran laundry (QRIS yang di-scan itu berupa barcode — anggap sama).\n
        TRUE (REKENING) - contoh:\n
        | rekening? | no rek? | nomor rekening? | minta rekening | rekening pembayaran? |\n
        | QRIS? | minta QRIS | QRIS pembayaran | link QRIS |\n
        | barcode? | minta barcode | barcode pembayaran | barcode nya kak | barcode nya ka | kirim barcode |\n
        | bisa tf kak | bisa transfer kak | mau tf | minta tf | boleh transfer ya | (permintaan singkat nomor/QRIS/cara bayar) |\n
        | transfer ke mana? | bayar ke mana? | mau transfer ke mana? | nomor untuk transfer? |\n
        | BCA/BRI/BNI rekeningnya? | nomor rekening BCA? |\n
        PENTING: Kata 'barcode' dalam konteks bayar/transfer laundry = sama dengan minta QRIS = REKENING.\n
        PENTING: 'bisa' / 'mau' / 'minta' + tf/transfer (tanpa konfirmasi sudah kirim) = minta info pembayaran = REKENING, BUKAN FALSE.\n
        \n
        FALSE (BUKAN REKENING):\n
        - User bertanya total tagihan (berapa total?) = TAGIHAN\n
        - User minta bon/nota = NOTA\n
        - CRITICAL: User memberitahu/pemberitahuan bahwa SUDAH transfer/mengirim = BUKAN REKENING, itu PENUTUP. Contoh: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim |"
    ],

    'TAGIHAN' => [
        'patterns' => [
            '/^\s*(bil+|tagihan)\s*$/i',
            // Permintaan kirim bill/tagihan: "bisa kirimkan bill saya", "kirim tagihan", "minta bill" / typo "bil"
            '/\b(kirim|kirimkan|minta|tolong)\s*(bil+|tagihan)\s*(saya|ku|punya)?/i',
            '/\b(bisa|boleh)\s*(kirim|kirimkan)?\s*(bil+|tagihan)/i',
            '/\b(bil+|tagihan)\s*(saya|ku|punya)?\s*(kirim|kirimkan)?/i',
            // "laundry aku ada kk?", "laundry saya ada?" = tanya tagihan/bill punya saya (BUKAN "ada yang tinggal/luntur")
            '/\b(laundry|loundry)\s+(aku|saya|ku|punya\s+saya)\s+ada(?!\s+yang)\b/i',
            // "berapa kilo itu kak?", "brpa kilo kk?" = tanya berat order (kait tagihan/kg) — bukan daftar harga per item
            '/\b(brp|brpa|brapa|berapa)\s*kilo\b/i',
            // "brp kg pny sya kk?", "berapa kg punya saya" — singkatan kg (bukan harga per kg)
            '/\b(brp|brpa|brapa|berapa)\s*kg\b/i',
            '/\b(brp|brpa|brapa|berapa)\s*kg\b.{0,40}?\b(punya|pny|punya\s+saya|saya|ku|sy|sya)\b/iu',
            // "brp londry ku buk" / "berapa laundry ku kak" = tanya total/tagihan order (typo londry; sufisk pendek)
            '/^\s*\b(brp|brpa|brapa|berapa)\b\s+(laundry|loundry|londri|londry)\s+(ku|aku)(?:\s+(?:kak|kk|bang|buk|min|mbak|pak|bu|ka|dek))?\s*$/iu',
            '/^\s*\b(brp|brpa|brapa|berapa)\b\s+(laundry|loundry|londri|londry)\s+saya(?:\s+(?:kak|kk|bang|buk|min|mbak|pak|bu|ka|dek))?\s*$/iu',
        ],
        'ai_prompt' => "User menanyakan TOTAL BIAYA/TAGIHAN laundry (jumlah uang yang harus dibayar) ATAU meminta dikirimkan bill/tagihan, seperti:\n
        | berapa total punya saya? | brapa total strika/cuci tadi? | totalnya berapa? | berapa tagihan? | berapa biaya laundry saya? |\n
        | total berapa kak? | brp total? | berapa total cuci? | berapa biayanya? |\n
        | bisa kirimkan bill saya | halo kak bisa kirimkan bill saya | kirim tagihan | minta bill | minta bil | kirimkan tagihan saya |\n
        | bill | bil | tagihan | = keyword tegas tagihan = TAGIHAN\n
        | laundry aku ada kk? | laundry saya ada? | laundry punya saya ada? | = tanya tagihan/bill punya saya = TAGIHAN\n
        | berapa kilo itu kak? | brpa kilo itu kk? | brp kilo? | = tanya berat cucian/order (hubungan ke tagihan) = TAGIHAN, BUKAN FALSE\n
        | brp londry ku kak? | berapa laundry ku bang? | brp laundry saya? | = typo londry / tanya total tagihan cucian saya = TAGIHAN, BUKAN FALSE\n
        Jika user bertanya 'berapa' + (total/biaya/tagihan) atau (total/biaya) + 'berapa' = TAGIHAN\n
        Jika user minta/bisa kirimkan bill/bil/tagihan = TAGIHAN (permintaan bill/tagihan; typo 'bil' sama dengan 'bill')\n
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
         // === HANYA 3 kategori: (1) terima kasih (2) sudah bayar/lunas (3) ack singkat murni ===
         // Ack singkat murni — SELURUH pesan hanya ok/baik/sip/siap(+sapaan). JANGAN match "Ok kk, aku otw"
         '/^\s*\bok(?:e+)?\s+sia+p+\s*(kak|kk|bang|min|mbak|pak|bu|ya)?\s*\??\s*$/iu',
         '/^\s*\bok(?:e+)?\s*(kak|kk|bang|min|mbak|pak|bu)?\s*[.!?]*\s*$/iu',
         '/^\s*\bsia+p+\s*(kak|kk|bang|min|mbak|pak|bu|penya|punya|ya)?\s*\??\s*$/iu',
         '/^\s*\b(ok(?:e+|ey)?|baik+|sip+)(\s+(deh|lah|dong|ya))*(?:\s+(kak|kk|bang|min|mbak|pak|bu|mas|om|dek|nte|penya|punya))*\s*[.!?]*\s*$/iu',
         '/^\s*\b(iya+|ya+)(\s+(deh|lah|dong))*(?:\s+(kak|kk|bang|min|mbak|pak|bu|buk))*\s*[.!?]*\s*$/iu',
         '/^\s*\b(ok(?:e+)?|baik|sip)\s+(sia+p+|sip)(?:\s+(kak|kk|bang|min|mbak|pak|bu|ya))?\s*\??\s*$/iu',
         '/^\s*(gpp|gak\s*apa\s*apa|ga\s*apa\s*apa)(?:\s+(kak|kk|bang|min|mbak|pak|bu))?\s*[.!?]*\s*$/iu',
         // Konfirmasi pembayaran/transfer/lunas — boleh ada konteks (bukan hanya ack singkat)
         '/(telah berhasil mengirimkan|sudah transfer|sudah bayar|sudah kirim|sudah mengirim)\s*(ke\s*)?(rekening|rek)?/i',
         '/\b(udah|udh|sdh)\s+(transfer|bayar|tf|trf|kirim)\b/i',
         '/\bberikut\s+bukti\s+(lunas(\s+bayar)?|bayar|transfer|pembayaran|tf)\b/i',
         '/\bbukti\s+(lunas(\s+bayar)?|bayar|transfer|pembayaran|tf)\b/i',
         '/\blunas\s+bayar\b/i',
         '/\b(info\s+)?pelunasan\b/i',
         // Trailing ellipsis/emoji/punctuation OK: "Lunas ya kak...🙏"
         '/^\s*(sudah|udah|udh|sdh)\s+lunas(\s+(ya\s*)?(kak|kk|bang|min|mbak|pak|bu))?\s*[^\p{L}\p{N}]*$/iu',
         '/^\s*lunas(\s+ya)?(\s+(kak|kk|bang|min|mbak|pak|bu))?\s*[^\p{L}\p{N}]*$/iu',
         // Ucapan terima kasih (termasuk typo: trima ksih, trima kasih)
         '/\bma*ka*(s|c)(i|e)*h\b/i',
         '/\bte*ri*ma*ka*si*h\b/i',
         '/\b(trima|terima)\s+(kasih|ksih|ksh)\b/i',
         '/\btrimakasih\b/i',
         '/\b(trmksh|trm\s*ksh)\b/i',
         '/\btha*nks\b/i',
         '/\b(thx|tq|ty)\b/i',
         // Oke + ditunggu kabar + terima kasih = penutup (bukan minta "kabari ya")
         '/\bok(?:e+)?\b.{0,100}?\b(di\s*)?tungg[uo]\b.{0,40}?\b(kbr|kabar)\b.{0,60}?\b(trima|terima|makasih|thanks|thx)/iu',
         // WhatsApp reactions
         '/^reacted\s+[^\s]+$/i',
      ],
      'ai_prompt' => "User memberikan PENUTUP — HANYA 3 jenis berikut (selain itu = FALSE):\n
      (1) Ucapan terima kasih (termasuk typo): | terima kasih | makasih | thanks | thx | tq | trima ksih | trima kasih byk |\n
      (1b) Ack + tunggu kabar PASIF + terima kasih = PENUTUP: | Oke kk, di tunggu kbr ny dn trima ksih byk | oke kak ditunggu kabarnya dan terima kasih banyak |\n
      (2) Info pelunasan / sudah bayar / bukti transfer: | sudah transfer | sudah bayar | sudah lunas | lunas ya kak | berikut bukti bayar | bukti transfer | info pelunasan | telah berhasil mengirimkan ke rekening |\n
      (3) Ack singkat MURNI — SELURUH pesan hanya: | ok | oke | baik | sip | siap | ok kak | siap kk | ok siap | iya | ya | gpp | (opsional sapaan kak/kk/bang/min). TANPA kalimat tambahan.\n
      - EMOJI/REACTION SAJA: | ❤️ | 👍 | Reacted 👍 | = boleh PENUTUP.\n
      \n
      FALSE (BUKAN PENUTUP) - CRITICAL:\n
      - CRITICAL: 'Ok'/'Baik'/'Siap' + isi lain (otw, jemput, antar, mau, nanti, jadwal, info order) TANPA terima kasih = FALSE. Contoh: | Ok kk,aku otw ya kk | baik nanti dijemput | siap besok diantar | = BUKAN PENUTUP.\n
      - CRITICAL: Janji AKAN bayar/transfer (belum kirim): | nnti transfer | besok bayar | = FALSE. Hanya SUDAH bayar/transfer = PENUTUP.\n
      - CRITICAL: Rujukan order/jadwal: | yg tadi sore besok di ambil | = FALSE.\n
      - CRITICAL: Pesan >50 karakter selain konfirmasi bayar/lunas ATAU ucapan terima kasih = FALSE.\n
      - kabari ya / kabarin ya / infokan ya (minta CS update, TANPA penutup terima kasih) = FALSE.\n
      - BEDA: 'di tunggu kabarnya' / 'ditunggu kbr nya' + terima kasih = PENUTUP (pasif menunggu + closing), BUKAN 'kabari ya'.\n
      - Info proses (belum diambil, sudah diantar suami) = FALSE.\n
      - Komplain, permintaan, daftar item laundry, pertanyaan = FALSE.\n
      - Contoh FALSE: | Ok kk,aku otw ya kk | baik nanti jemput | siap diantar sore | kabari ya kak | nanti saya jemput |"
   ],

    'STATUS' => [
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
            '/\b(sudah|udah|udh|dah+|dh)\s*sia+p+\b/i',  // dh siap, udh siap, laundry saya udh siap?
            // siap+kak/kk/... = STATUS hanya jika BUKAN sekadar pesan pendek "siap kak" (= PENUTUP)
            '/(?!\A\s*\bsia+p+\s*(?:kak|kk|bang|min|mbak|pak|bu|penya|punya|ya)\s*\??\s*\z)\b(siap|sia+p+)\s*(kak|kk|bang|min|mbak|pak|bu|penya|punya)/iu',
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
        'ai_prompt' => "User menanyakan ATAU memberitahu status/progress laundry SAAT INI (sudah siap atau belum), BUKAN tanya estimasi kapan siap.\n
        Contoh kalimat tanya STATUS (pilih STATUS):\n
        | sudah selesai? | udh selesai kah? | bisa diambil? | sudah jadi? | sudah siap kak? | udah siap kak? |\n
        | sudah bisa diambil? | udh bisa d jemput kak? | udh bisa di jemput? | pakaian harian apa sdh bisa dijemput? | yang strika sdh bisa dijemput? |\n
        | atas nama IVAN udah? | atas nama X sudah? | laundry [nama] udah? | punya [nama] sudah? |\n
        \n
        KONFIRMASI/PEMBERITAHUAN status (sudah siap) — bedakan dari ack penutup:\n
        | sudah siap | udh siap | dah siap | dahh siapp | siapp kak | sudah jadi | ready |\n
        | laundry saya udh siap | loundry saya udh siap mbak | dh siap kk penya ku | siap kk penya | kak dahh siapp kak | dah siapp |\n
        (Hanya 'siap kak' / 'ok siap' / 'ok siap kak' tanpa konteks tanya status = intent PENUTUP, bukan STATUS.)\n
        \n
        PRIORITAS: 'apakah sudah/sudh/siap ... laundry/laundri/cucian saya?', 'sudh siap laundri?', 'udh siap cucian?' = tanya STATUS order (pilih STATUS).\n
        PENTING:\n
        - Pola 'yang/pakaian/laundry/... apa ... bisa dijemput/diambil?' (boleh tanpa sdh/udh) = tanya status order siap diambil = STATUS\n
        - Jika user bertanya 'atas nama [nama] udah/sudah?' = STATUS (tanya status laundry atas nama tertentu)\n
        - Jika user memberitahu/konfirmasi 'sudah siap' / 'dah siap' / 'siapp' / 'udah siap kak?' = STATUS (bukan sekadar 'siap kak' / 'ok siap' pendek = PENUTUP)\n
        \n
        FALSE (BUKAN STATUS) — pilih ESTIMASI_SELESAI:\n
        - Tanya KAPAN / JAM BERAPA siap/selesai/bisa diambil/dijemput (butuh estimasi waktu) = ESTIMASI_SELESAI, BUKAN STATUS. Contoh: | kapan siap? | jam berapa siap? | siapnya kapan? | kira jam berapa bisa dijemput kak? |\n
        \n
        FALSE (BUKAN STATUS) - CRITICAL:\n
        - Belum dapat notifikasi/nota via WA setelah sudah antar laundry = follow-up BON/NOTA digital = NOTA, BUKAN STATUS.\n
        - 'Masih bisa siap gak?' / 'masih bisa siap?' + konteks mau bawa/antar laundry = JAM_OPERASIONAL.\n
        - 'tutup tgl berapa?' / 'tutup tanggal brp?' = JAM_OPERASIONAL (bukan STATUS)\n
        - atau yang menurut anda sangat yakin sebagai pertanyaan/pemberitahuan status laundry SAAT INI"
    ],

    'ESTIMASI_SELESAI' => [
        // Tanya jam/kapan siap → case 4 (butuh petugas). Fase "selesai" tetap data reply di handler.
        'case' => 4,
        'notify' => true,
        'patterns' => [
            // kapan / jam berapa + siap/selesai/jadi (estimasi, bukan "sudah siap?")
            '/\b(kapan|kpn|jam\s*(brp|brpa|berapa)|brp|berapa)\b.{0,50}?\b(siap|sia+p+|selesai|jadi)\b/iu',
            '/\b(siap|sia+p+|selesai)(nya)?\b.{0,30}?\b(kapan|kpn|jam|brp|berapa)\b/iu',
            // kira(-kira) jam/kapan ... bisa dijemput/diambil / siap
            '/\b(kira|kira[\s\-]*kira)\b.{0,60}?\b(jam|kapan|kpn|brp|berapa)\b.{0,60}?\b(bisa|boleh)?\s*(di\s*)?(ambil|jemput|siap|selesai)\b/iu',
            // jam/kapan berapa bisa (di)ambil / (di)jemput — estimasi siap diambil (bukan minta kurir)
            '/\b(kapan|kpn|jam\s*(brp|brpa|berapa)|brp|berapa)\b.{0,50}?\b(bisa|boleh)\s*(di\s*)?(ambil|jemput)\b/iu',
            '/\b(jam\s*)?(brp|brpa|berapa)\s*bisa\s*(di\s*)?(jemput|ambil)\b/iu',
            // "kapan bisa diambil?" / "kapan bisa dijemput?"
            '/\b(kapan|kpn)\s+(bisa|boleh)\s*(di\s*)?(ambil|jemput)\b/iu',
            // REQUEST selesai jam spesifik (bukan tanya jam berapa): "bisa siap jam 10", "minta selesai jam 14.30"
            '/\b(bisa|boleh|minta|tolong|mau|mohon)\b.{0,40}\b(siap|selesai|jadi)\b.{0,30}\bjam\s*\d{1,2}/iu',
            '/\b(siap|selesai|jadi)\b.{0,30}\bjam\s*\d{1,2}([.:]\d{1,2})?\b/iu',
            '/\bjam\s*\d{1,2}([.:]\d{1,2})?\b.{0,30}\b(siap|selesai|jadi)\b/iu',
        ],
        'ai_prompt' => "User menanyakan ESTIMASI waktu selesai ATAU meminta SELESAI pada jam tertentu — BUKAN tanya apakah sudah siap sekarang, BUKAN minta kurir jemput/antar.\n
        TRUE (ESTIMASI_SELESAI) — dua sub-jenis (keduanya pilih ESTIMASI_SELESAI):\n
        A) TANYA estimasi (jam berapa / kapan): | kapan siap? | jam berapa siap? | siapnya kapan? | kira jam berapa bisa dijemput? |\n
        B) REQUEST selesai jam spesifik: | bisa siap jam 10? | minta selesai jam 14 | boleh siap jam 16.30 besok? | siap jam 11 mau ke medan |\n
        Untuk (B) WAJIB ada angka jam (10 / 14.30), BUKAN 'jam berapa'.\n
        \n
        CRITICAL - bedakan dari STATUS:\n
        - 'sudah siap kak?' / 'udah siap?' / 'bisa diambil?' (tanpa kapan/jam berapa dan tanpa jam angka) = STATUS.\n
        \n
        CRITICAL - bedakan dari MINTA_JEMPUT_ANTAR:\n
        - 'kira jam berapa bisa dijemput' = ESTIMASI_SELESAI.\n
        - 'tolong jemput di kamar 212' = MINTA_JEMPUT_ANTAR.\n
        \n
        CRITICAL - bedakan dari JAM_OPERASIONAL:\n
        - 'jam berapa buka/tutup?' = JAM_OPERASIONAL, BUKAN ESTIMASI_SELESAI."
    ],

    'HARGA' => [
        'patterns' => [
            '/^\s*(harga|price)\s*$/i',
            // Pricelist / daftar harga (termasuk "pricelistnya", "boleh dibantu pricelistnya kak")
            '/\bpricelist\w*\b/iu',
            '/\bprice\s*list\w*\b/iu',
            '/\b(daftar|list)\s+harga\w*\b/iu',
            // "berapa 1 boneka kak?" / "berapa 2 baju" = harga per item, bukan tagihan total
            '/\bberapa\s+(\d+\s+)?(biji|pcs|pc|buah|lembar\s+)?(boneka|baju|celana|handuk|selimut|jaket|sepatu|tas|karpet|sprei|bedcover|gorden|kemeja|rok|gaun|jas|hoodie|sweater|topi|sarung|mukena|jilbab|kerudung)\b/iu',
            // "berapa boneka?" tanpa angka
            '/\bberapa\s+(harga|biaya|cuci|setrika|strika|gosok)?\s*(boneka|baju|celana|handuk|selimut|jaket|sepatu|tas|karpet|sprei|bedcover|gorden|kemeja|rok|gaun|jas)\b/iu',
            // Ongkos/ongkir by durasi (sehari, 1–3 hari, dll.) atau by jenis layanan (regular/ekspres/kilat) = tarif di data harga, bukan minta kurir
            '/\b(brp|brpa|brapa|berapa|harga|biaya|tarif)\b.{0,140}?\b(ongkos|ongkir|ong\s*kos)\b.{0,100}?\b(sehari|se\s*hari|satu\s*hari|dua\s*hari|tiga\s*hari|\d{1,2}\s*hari)\b/iu',
            '/\b(sehari|se\s*hari|satu\s*hari|dua\s*hari|tiga\s*hari|\d{1,2}\s*hari)\b.{0,140}?\b(brp|brpa|brapa|berapa|harga|biaya|tarif)\b.{0,100}?\b(ongkos|ongkir|ong\s*kos)\b/iu',
            '/\b(brp|brpa|brapa|berapa|harga|biaya|tarif)\b.{0,140}?\b(ongkos|ongkir|ong\s*kos)\b.{0,100}?\b(regular|reguler|ekspres|ekspress|express|kilat)\b/iu',
            '/\b(regular|reguler|ekspres|ekspress|express|kilat)\b.{0,140}?\b(brp|brpa|brapa|berapa|harga|biaya|tarif)\b.{0,100}?\b(ongkos|ongkir|ong\s*kos)\b/iu',
        ],
        'ai_prompt' => "User menanyakan harga/biaya laundry PER ITEM atau PER KILO, seperti:\n
        | berapa harga? | berapa biaya? | harga berapa? | biaya berapa? |\n
        | berapa harga baju? | berapa harga celana? | berapa harga handuk? | berapa harga boneka? | berapa harga sepatu? | berapa harga selimut? | berapa harga jaket? |\n
        | berapa 1 boneka kak? | berapa 2 baju? | boneka brp? | cuci boneka berapa? |\n
        | berapa harga per kilo? | berapa biaya per kg? | harga per kilo berapa? |\n
        | minta pricelist | boleh dibantu pricelistnya kak? | kirim daftar harga | list harga dong | price list ya |\n
        PENTING: \n
        - 'berapa kilo itu?' / 'brp kilo kak?' TANPA kata harga/biaya = tanya berat order = TAGIHAN, BUKAN HARGA.\n
        - Jika user bertanya 'berapa' + (harga/biaya) + (item laundry atau per kilo) = HARGA\n
        - Jika user bertanya 'berapa' + angka + item (boneka, baju, dll) = HARGA (harga per item), BUKAN TAGIHAN total order\n
        - CRITICAL - TRUE (HARGA): Meminta PRICELIST / PRICE LIST / DAFTAR HARGA / LIST HARGA (bahasa Inggris atau Indonesia) = minta daftar tarif laundry = HARGA, BUKAN FALSE. Contoh: | boleh dibantu pricelistnya kak | minta pricelist | share price list | daftar harga dong |\n
        - CRITICAL - TRUE (HARGA): Tanya ONGKOS/ONGKIR sekaligus DURASI proses (1/2/3 hari, sehari, satu hari, dll.) di awal atau akhir kalimat = tanya TARIF layanan sesuai SLA di data harga = HARGA. Contoh: | Klu 1 hari brp ongkos nya | kalau 2 hari berapa ongkos | sehari brp ongkir kak | = HARGA, BUKAN MINTA_JEMPUT_ANTAR.\n
        - CRITICAL - TRUE (HARGA): Tanya ONGKOS/ONGKIR + JENIS LAYANAN (regular, ekspres, express, kilat) = tarif varian layanan di data harga = HARGA. Contoh: | berapa ongkos regular? | ongkos kilat brp? | brp ongkos ekspres | = HARGA, BUKAN MINTA_JEMPUT_ANTAR.\n
        - Jika user bertanya 'berapa' + (ongkir/ongkos/biaya antar/biaya jemput) TANPA durasi hari DAN TANPA sebut regular/ekspres/kilat (murni ongkos antar/jemput kurir) = MINTA_JEMPUT_ANTAR\n
        - Jika user bertanya 'berapa' + (berat saja tanpa item) = bisa NOTA/TAGIHAN, bedakan konteks\n
        \n
        CRITICAL - FALSE (BUKAN HARGA) - harga barang tambahan/ritel (bukan tarif cuci/setrika laundry):\n
        - Jika pesan menyebut parfum / plastik (kantong plastik) / pewangi / hanger / tissue / barang kemasan = BUKAN HARGA. Contoh: | berapa harga parfum kak? | berapa harga plastik? | sekarang berapa harga parfum? | harga kantong plastik? |\n
        - Intent khusus harga barang itu nanti terpisah; untuk sekarang jawab FALSE agar CS yang tangani.\n
        \n
        atau yang menurut anda sangat yakin sebagai pertanyaan harga/biaya laundry PER ITEM atau PER KILO (bukan barang tambahan di atas)"
    ],

    'HARGA_PAKET_D' => [
        'patterns' => [
            // Delivery di awal: "antar jemput harga paket member"
            '/\b(antar|jemput|delivery|antar\s*jemput|jemput\s*antar)\b.{0,150}?\b(harga\s+paket\s+member|harga\s+paket|harga\s+member|paket\s+member|paket\s+(?:laundry|londri|laundri|loundry|loundri))\b/iu',
            // Delivery di akhir: "paket member antar jemput", "harga paket include delivery"
            '/\b(harga\s+paket\s+member|harga\s+paket|harga\s+member|paket\s+member|paket\s+(?:laundry|londri|laundri|loundry|loundri))\b.{0,150}?\b(antar|jemput|delivery|antar\s*jemput|jemput\s*antar)\b/iu',
        ],
        'ai_prompt' => "User menanyakan HARGA PAKET/MEMBER/LANGGANAN/DEPOSIT yang INCLUDE layanan ANTAR-JEMPUT (delivery).\n
        TRUE (HARGA_PAKET_D) - HARUS ADA kata paket/member/langganan/deposit DAN konteks antar/jemput/delivery:\n
        | berapa harga paket yang include antar jemput? | harga member pakai antar? | paket bulanan sama ongkir? |\n
        | daftar harga paket + antar jemput | paket member antar jemput | harga paket laundry sekalian antar jemput |\n
        | paket delivery berapa? | harga deposit member + jemput |\n
        \n
        CRITICAL - FALSE (BUKAN HARGA_PAKET_D):\n
        - Tanya harga paket/member TANPA menyebut antar/jemput/ongkir paket/delivery = HARGA_PAKET (bukan HARGA_PAKET_D).\n
        - Contoh FALSE: | berapa harga paket? | harga member? | ada paket bulanan? | paket setrika aja berapa? |\n
        - Instruksi kurir saja tanpa tanya harga paket: | tolong dijemput | minta antar baju kamar 212 | = MINTA_JEMPUT_ANTAR.\n
        - 'Setrika aja' tanpa kata paket/member = BUKAN HARGA_PAKET_D.\n
        \n
        PENTING:\n
        - Ini pertanyaan TARIF paket varian include antar-jemput, BUKAN permintaan kurir jemput order sekarang.\n
        - Jika user bertanya harga PER ITEM atau PER KILO (baju, celana, per kg) = HARGA (bukan HARGA_PAKET_D)"
    ],

    'HARGA_PAKET' => [
        'patterns' => [
            '/\bharga\s+paket\s+member\b/iu',
            '/\bharga\s+paket\b/iu',
            '/\bharga\s+member\b/iu',
            '/\bpaket\s+member\b/iu',
            '/\bpaket\s+(?:laundry|londri|laundri|loundry|loundri)\b/iu',
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
        CRITICAL - FALSE (bukan HARGA_PAKET — pakai HARGA_PAKET_D):\n
        - User bertanya harga paket/member/langganan/deposit sekaligus antar/jemput/ongkir paket/delivery/include antar = HARGA_PAKET_D, BUKAN HARGA_PAKET.\n
        - Contoh: | berapa harga paket yang include antar jemput? | harga member pakai antar? | paket member antar jemput | paket bulanan sama ongkir? | daftar harga paket + antar jemput |"
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
         // "jam berapa bisa diantar?" = jadwal kurir antar (bukan estimasi siap diambil — itu ESTIMASI_SELESAI)
         '/\b(jam\s*)?(brp|brpa|berapa)\s*bisa\s*(di)?antar\b/i',
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
      - Minta jemput ATAU antar (beda: jemput = ambil dari lokasi customer, antar = antar ke lokasi customer).\n
      - Follow-up session (konfirmasi lokasi/tarif/jam/setuju/instant) tetap MINTA_JEMPUT_ANTAR.\n
      HARUS ADA kata permintaan atau pertanyaan atau instruksi jemput ke lokasi:\n
      - Kata kunci: tolong/minta/bisa/boleh/dong/kapan/berapa + jemput/antar\n
      - tolong jemput, minta dijemput, bisa diantar?, boleh dijemput?, kapan diantar?\n
      - jam brp bsk diantarnya?, jam berapa besok diantar?, kapan diantarnya? = tanya jadwal pengantaran order = MINTA_JEMPUT_ANTAR\n
      - bisa jemput kak?, nanti bisa jemput kak?, jemput dong, antar ya dong\n
      - Singkatan: bs jmpt baju?, bs jemput? = sama dengan bisa jemput = MINTA_JEMPUT_ANTAR\n
      - katanya mau antar, katanya mau jemput = relay/konfirmasi permintaan antar = MINTA_JEMPUT_ANTAR\n
      - brp ongkirnya?, berapa ongkosnya?, brp ong nya kak?, biaya antar? — HANYA jika TANPA durasi (1/2/3 hari, sehari) DAN TANPA jenis layanan (regular/ekspres/kilat). Jika ada '1 hari'/'sehari'/durasi + ongkos ATAU regular/ekspres/kilat + ongkos = itu tanya TARIF di harga = HARGA, BUKAN MINTA_JEMPUT_ANTAR.\n
      - CRITICAL: Instruksi ambil/jemput baju/laundry/kain/bedcover/sprei dari LOKASI (kamar hotel/kost, depan kamar, rumah sakit, sekolah, alamat/jalan) = MINTA_JEMPUT_ANTAR. Contoh: | kk ambil baju kotor sama bedcover di depan kamar 212 | ambil laundry di hotel | jemput di kamar 305 | ambil di RS |\n
      - CRITICAL - FALSE: Minta SATU jenis pakaian/item (baju dinas, seragam, dll.) diambil/dulukan DULU dari order/cucian yang sudah di laundry — prioritas item, BUKAN minta kurir jemput-antar = PERMINTAAN.\n
      \n
      FALSE (BUKAN MINTA JEMPUT/ANTAR) - SANGAT PENTING:\n
      - Tanya STATUS order (sudah/sdh/udh bisa dijemput/diambil) termasuk per kategori: | pakaian harian apa sdh bisa dijemput? | yang cuci setrika udh bisa dijemput? | = STATUS, BUKAN minta kurir.\n
      - Tanya ESTIMASI selesai / kapan siap diambil: | kapan siap? | jam berapa siap? | kira jam berapa bisa dijemput kak? | jam berapa bisa dijemput? | kapan bisa diambil? | = ESTIMASI_SELESAI, BUKAN minta kurir.\n
      - User yang akan MENGAMBIL SENDIRI: | mau jemput | saya jemput | aku ambil | awak jemput | nanti saya jemput |\n
      - Konfirmasi/Pemberitahuan: | baik nanti dijemput | ok sore diantar | iya nanti akan dijemput | siap dijemput |\n
      - Hanya memberitahu jadwal tanpa permintaan: | nanti sore dijemput | besok diantar | jam 2 dijemput |\n
      - CRITICAL: 'masih bisa antar laundry?' / 'masih bisa antar?' = tanya AVAILABILITAS operasional = JAM_OPERASIONAL (bukan MINTA_JEMPUT_ANTAR). Kata 'masih' membedakan: tanya masih buka/bisa vs permintaan.\n
      - CRITICAL: 'kira jam berapa bisa dijemput' / 'jam berapa bisa dijemput' (tanpa lokasi kamar/hotel) = tanya kapan order SIAP diambil = ESTIMASI_SELESAI, BUKAN MINTA_JEMPUT_ANTAR.\n
      - CRITICAL: 'Mau jemput' / 'saya jemput nanti' = User SENDIRI yang akan mengambil = FALSE (bedakan dari 'ambil baju di kamar X' = minta kurir)\n
      - CRITICAL: Instruksi 'ambil/jemput' + lokasi (kamar/hotel/RS/sekolah/alamat) = MINTA_JEMPUT_ANTAR walaupun TANPA kata tolong/minta/bisa — itu permintaan kurir ke alamat.\n
      - CRITICAL - FALSE: Pertanyaan HARGA PAKET/MEMBER/DEPOSIT + antar/jemput/ongkir paket/delivery (berapa harga paket include antar? harga member pakai antar? paket member antar jemput) = HARGA_PAKET_D, BUKAN MINTA_JEMPUT_ANTAR.\n
      - CRITICAL - FALSE: Tanya ongkos/ongkir + durasi hari (1/2/3 hari, sehari) ATAU + regular/ekspres/kilat = HARGA (tarif layanan), BUKAN MINTA_JEMPUT_ANTAR.\n
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
        - 'jam berapa bisa jemput?' / 'kira jam berapa bisa dijemput?' = tanya estimasi kapan order siap diambil = ESTIMASI_SELESAI (bukan JAM_OPERASIONAL, bukan MINTA_JEMPUT kecuali ada lokasi kamar/hotel)\n
        - 'jam brp bsk diantarnya?' / 'jam berapa besok diantar?' / 'kapan diantarnya?' = tanya jadwal PENGANTARAN order = MINTA_JEMPUT_ANTAR (bukan JAM_OPERASIONAL)\n
        - 'bisa antar laundry?' / 'tolong antar laundry' / 'bs jmpt baju?' (TANPA 'masih') = permintaan jemput/antar = MINTA_JEMPUT_ANTAR\n
        - Contoh FALSE: 'jam berapa?', 'jam brp kak?' (tanpa buka/tutup) | 'jam berapa diantar?', 'kapan dijemput?', 'jam brp bsk diantarnya?'"
    ],

    'REMINDER' => [
        'patterns' => [
            '/^\s*(reminder|remind|ingatkan|ingat|pengingat)\s*$/i',
        ],
    ],

    // Access Key per user (staff terdaftar di tabel user) — regex only, tanpa AI
    'KEY' => [
        'patterns' => [
            '/^\s*key\s*$/i',
            '/^\s*key\s+new\s*$/i',
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

    'SALDO_YCLOUD' => [
        'patterns' => [
            '/^(saldo|cek|info)\s*(ycloud)$/i',
        ],
    ],

    'INFO_FONNTE' => [
        'patterns' => [
            '/^(cek|info)\s*(fonnte|fonte)$/i',
        ],
    ],

    'CEK_QRIS' => [
        'patterns' => [
            '/^\s*cek\s+qris\s+(\d{2})\.(\d{2})\s+(\d+)\s*$/i',
            '/^\s*cek\s+qris\b/i',
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
            '/^gaji (tf|transfer)$/i',
        ],
    ],
];
