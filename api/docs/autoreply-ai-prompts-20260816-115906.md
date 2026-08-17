# AI Prompts — Auto Reply Keywords

Dibuat: 2026-08-16 11:59:06

=== PEMBUKA ===

User HANYA memberi sapaan awal singkat tanpa permintaan/isi pesan lain.

Contoh: | halo | hai | ping | pagi | siang | malam | sore | kak | bang | pak | bu | assalamualaikum | assalamualaikum kak |

PENTING: JIKA sapaan diikuti kalimat permintaan (misal: 'Bang, baju dulukan', 'Kak, jemput ya'), ini FALSE.

PENTING: Jika sapaan + permintaan bill/tagihan (bisa kirimkan bill, kirim tagihan, minta bill) = FALSE.

PENTING: Jika sapaan + permintaan nota/bon (minta nota, minta bon, kirim struk) = FALSE.

CRITICAL: Pesan mengandung tanda tanya (?) = tidak sekadar sapaan PEMBUKA. Contoh: 'Berarti sudah masuk kak?'

FALSE jika: | kabari ya kak | kabarin ya | infokan ya kk | kasih kabar ya | = minta update/kabar, tidak sapaan awal — pilih FALSE (tidak PENUTUP, biarkan CS).

FALSE jika: Balasan salam / jawaban atas salam = tidak termasuk pembuka percakapan. Contoh: | waalaikumsalam | walaikumsalam | wa alaikum salam | waalaikum salam | waalaikumussalam | = FALSE (tidak sama dengan assalamualaikum).

=== NOTA ===

User meminta BON/NOTA/STRUK (dokumen bukti terima) sebagai fisik/cetak, ATAU menindaklanjuti karena bukti/nota belum masuk WhatsApp, seperti:

| bon | nota | struk | bukti terima | minta bon | minta nota | minta struk |

TRUE jika: follow up bukti di WA:

- Jika ada pola: (laundry/loundry) + (belum/blm/belom) + 'ada masuk' + (wa/whatsapp) = TRUE. Contoh: | Laundry strika hari ini blm ada masuk wa nya | laundry cuci belum ada masuk whatsapp | loundry saya blm ada masuk wa |

- Kalimat singkat notifikasi WA belum masuk: | Wa nya blm masuk | wa blm masuk | blm masuk wa | = TRUE (follow-up bukti/nota di WA).

- Sudah antar/nitip laundry ke outlet tapi belum dapat notifikasi/nota lewat WA = TRUE (follow-up bukti terima digital). Contoh: | Tadi malam saya antar londri tp sampai siang belum dapat notifikasi via wa | sudah antar laundry kemarin belum ada notif wa | belum dapat notifikasi wa padahal sudah antar cucian |

- Waktu + belum di WA: user merujuk order/jam (tadi sore, tadi pagi, kemarin, hari Jumat, yg td sore, dll) lalu 'blm di wa' / 'belum di whatsapp' = follow-up NOTA/notifikasi digital belum masuk = TRUE. Contoh: | Kak yg td sore blm di wa ya brp nya | yang tadi pagi blm di wa | kemarin blm di wa kak | = TRUE. 'Brp nya' di sini = tanya notifikasi/nota (kok belum), tidak tanya total rupiah tagihan.

- TRUE jika: Pola minta INFO + (laondri/laundry) + yang saya antar + pagi/siang/sore/malam + tadi/kemarin = follow-up NOTA/bukti order setelah antar (tidak sekadar sapaan). Contoh: | Tolong infonya. Laondri yang saya antar pagi tadi | infonya laundry yang saya antar siang tadi kak | = TRUE.

- TRUE jika: User merujuk cucian yang BARU/KEMARIN diantar/masukkan/diserahkan + sekaligus menjelaskan PILIHAN LAYANAN (cuci setrika, setrika aja, reguler, ekspres, manual, biasa, dll) = konfirmasi detail order untuk nota/bukti terima = TRUE. HARUS ada pilihan layanan, tidak sekadar daftar item. Contoh: | Kak, yg saya masukkan tadi, cuci setrika manual n cuci setrika biasa, reguler yaaa | kain yang saya antar tadi malam, itu setrika aja ya | yang saya antar pagi ini cuci setrika ya kak | pilih yang ekspres untuk laundry yang saya titip tadi | cuci setrika ya kak untuk yang saya bawa tadi siang | = TRUE.

- Pola rujukan: (yang/yg) (saya/aku) (masukkan/antar/titip/bawa) + (tadi/kemarin/pagi/siang/sore/malam) ATAU (kain/pakaian/cucian) (yang) (saya) (antar/masukkan) + waktu.

- Pola layanan (WAJIB untuk TRUE di atas): cuci setrika, setrika aja, reguler/regular, ekspres/express, kilat, manual, biasa, pilih yang ekspres, itu setrika aja. Kata laundry/loundry/item saja tidak pilihan layanan.

- Ini komplain/follow-up nota struk bon belum dikirim ke WA customer = TRUE.

FALSE jika: PENTING:

FALSE jika: Hanya memberitahu ada laundry kemarin/hari ini + daftar ITEM (celana putih, baju, dll) TANPA minta bon/nota/struk DAN TANPA sebut pilihan layanan (cuci setrika/reguler/ekspres/dll) = FALSE. Contoh: | saya kn kmrin ada loundry dlm nya ada clna putih | kemarin ada laundry ada celana putih | = FALSE.

- User menanya 'berapa total?' / 'berapa biaya?' / 'total berapa?' / 'brapa total strika tadi?' murni tanya JUMLAH UANG (tanpa konteks blm di wa / nota belum masuk) = FALSE.

- Jika pesan jelas 'blm di wa' / 'belum di whatsapp' dengan konteks waktu (tadi sore, dll) = NOTA walaupun ada kata 'berapa' atau 'brp'.

=== REKENING ===

User menanyakan REKENING PEMBAYARAN atau meminta QRIS/BARCODE untuk pembayaran laundry (QRIS yang di-scan itu berupa barcode — anggap sama).

TRUE jika: contoh:

| rekening? | no rek? | nomor rekening? | minta rekening | rekening pembayaran? |

| QRIS? | minta QRIS | QRIS pembayaran | link QRIS |

| barcode? | minta barcode | barcode pembayaran | barcode nya kak | barcode nya ka | kirim barcode |

| bisa tf kak | bisa transfer kak | mau tf | minta tf | boleh transfer ya | (permintaan singkat nomor/QRIS/cara bayar) |

| transfer ke mana? | bayar ke mana? | mau transfer ke mana? | nomor untuk transfer? |

| BCA/BRI/BNI rekeningnya? | nomor rekening BCA? |

PENTING: Kata 'barcode' dalam konteks bayar/transfer laundry = sama dengan minta QRIS = TRUE.

PENTING: 'bisa' / 'mau' / 'minta' + tf/transfer (tanpa konfirmasi sudah kirim) = minta info pembayaran = TRUE.

FALSE jika: User bertanya total tagihan (berapa total?) = TAGIHAN

- User minta bon/nota = NOTA

- CRITICAL: User memberitahu/pemberitahuan bahwa SUDAH transfer/mengirim = FALSE, itu PENUTUP. Contoh: | telah berhasil mengirimkan ke rekening | sudah transfer | sudah bayar | sudah kirim |

=== TAGIHAN ===

User menanyakan TOTAL BIAYA/TAGIHAN laundry (jumlah uang yang harus dibayar) ATAU meminta dikirimkan bill/tagihan, seperti:

| berapa total punya saya? | brapa total strika/cuci tadi? | totalnya berapa? | berapa tagihan? | berapa biaya laundry saya? |

| total berapa kak? | brp total? | berapa total cuci? | berapa biayanya? |

| bisa kirimkan bill saya | halo kak bisa kirimkan bill saya | kirim tagihan | minta bill | minta bil | kirimkan tagihan saya |

| bill | bil | tagihan | = keyword tegas tagihan = TAGIHAN

| laundry aku ada kk? | laundry saya ada? | laundry punya saya ada? | = tanya tagihan/bill punya saya = TAGIHAN

| berapa kilo itu kak? | brpa kilo itu kk? | brp kilo? | = tanya berat cucian/order (hubungan ke tagihan) = TRUE

| brp londry ku kak? | berapa laundry ku bang? | brp laundry saya? | = typo londry / tanya total tagihan cucian saya = TRUE

Jika user bertanya 'berapa' + (total/biaya/tagihan) atau (total/biaya) + 'berapa' = TAGIHAN

Jika user minta/bisa kirimkan bill/bil/tagihan = TRUE (permintaan bill/tagihan; typo 'bil' sama dengan 'bill')

Jika user tanya 'laundry aku/saya ada?' = tanya tagihan punya saya = TAGIHAN

FALSE jika: NOTA:

- Follow-up nota/notifikasi belum masuk WA: 'tadi sore blm di wa', 'yg td sore blm di wa ya brp nya', 'kemarin blm di whatsapp' = tanya bukti/notifikasi di WA = FALSE (walaupun ada 'berapa'/'brp').

FALSE jika: HARGA:

- User bertanya HARGA cuci/setrika PER ITEM (order belum tentu ada): | berapa 1 boneka kak? | berapa 2 baju? | boneka berapa? | brp harga boneka? |

- Pola: 'berapa' + (angka) + nama barang laundry (boneka, baju, celana, handuk, selimut, jaket, sepatu, tas, karpet, sprei, dll) = tanya DAFTAR HARGA = FALSE, tidak tagihan order.

- FALSE: 'berapa kilo itu?' / 'brp kilo kak?' (tanya berat order) = TAGIHAN — bedakan dari 'berapa harga per kilo?' (= HARGA).

=== PENUTUP ===

User memberikan PENUTUP — HANYA 3 jenis berikut (selain itu = FALSE):

(1) Ucapan terima kasih (termasuk typo): | terima kasih | makasih | makasi | makaci | makaseh | mksh | mksh byk | thanks | thx | tq | trima ksih | trima kasih byk |

(1b) Ack + tunggu kabar PASIF + terima kasih = PENUTUP: | Oke kk, di tunggu kbr ny dn trima ksih byk | oke kak ditunggu kabarnya dan terima kasih banyak |

(2) Info pelunasan / sudah bayar / bukti transfer: | sudah transfer | sudah bayar | sudah lunas | lunas ya kak | berikut bukti bayar | bukti transfer | info pelunasan | telah berhasil mengirimkan ke rekening | Uda saya bayar ka barusan | udah saya bayar | saya sudah bayar barusan |

(3) Ack singkat MURNI — SELURUH pesan hanya: | ok | okk | okkk | oke | baik | sip | siap | ok kak | okkk kk | siap kk | ok siap | iya | ya | gpp | (opsional sapaan kak/kk/bang/min). TANPA kalimat tambahan.

- EMOJI/REACTION/STICKER SAJA: | ❤️ | 👍 | Reacted 👍 | 🎨 Sticker | Sticker | = boleh PENUTUP (jenis lainnya).

FALSE jika: CRITICAL:

- CRITICAL: 'Ok'/'Baik'/'Siap' + isi lain (otw, jemput, antar, mau, nanti, jadwal, info order) TANPA terima kasih = FALSE. Contoh: | Ok kk,aku otw ya kk | baik nanti dijemput | siap besok diantar | = FALSE.

- CRITICAL: Janji AKAN bayar/transfer (belum kirim): | nnti transfer | besok bayar | = FALSE. Hanya SUDAH bayar/transfer = TRUE.

- CRITICAL: Rujukan order/jadwal: | yg tadi sore besok di ambil | = FALSE.

- CRITICAL: Pesan >50 karakter selain konfirmasi bayar/lunas ATAU ucapan terima kasih = FALSE.

- kabari ya / kabarin ya / infokan ya (minta CS update, TANPA penutup terima kasih) = FALSE.

- BEDA: 'di tunggu kabarnya' / 'ditunggu kbr nya' + terima kasih = TRUE (pasif menunggu + closing), tidak 'kabari ya'.

- Info proses (belum diambil, sudah diantar suami) = FALSE.

- Komplain, permintaan, daftar item laundry, pertanyaan = FALSE.

- Contoh FALSE: | Ok kk,aku otw ya kk | baik nanti jemput | siap diantar sore | kabari ya kak | nanti saya jemput |

=== STATUS ===

User menanyakan ATAU memberitahu status/progress laundry SAAT INI (sudah siap atau belum), tidak tanya estimasi kapan siap.

Contoh kalimat tanya STATUS (pilih STATUS):

| sudah selesai? | udh selesai kah? | bisa diambil? | sudah jadi? | sudah siap kak? | udah siap kak? |

| sudah bisa diambil? | udh bisa d jemput kak? | udh bisa di jemput? | pakaian harian apa sdh bisa dijemput? | yang strika sdh bisa dijemput? |

| atas nama IVAN udah? | atas nama X sudah? | laundry [nama] udah? | punya [nama] sudah? |

KONFIRMASI/PEMBERITAHUAN status (sudah siap) — bedakan dari ack penutup:

| sudah siap | udh siap | dah siap | dahh siapp | siapp kak | sudah jadi | ready |

| laundry saya udh siap | loundry saya udh siap mbak | dh siap kk penya ku | siap kk penya | kak dahh siapp kak | dah siapp |

(Hanya 'siap kak' / 'ok siap' / 'ok siap kak' tanpa konteks tanya status = intent PENUTUP, tidak STATUS.)

PRIORITAS: 'apakah sudah/sudh/siap ... laundry/laundri/cucian saya?', 'sudh siap laundri?', 'udh siap cucian?' = tanya STATUS order (pilih STATUS).

PENTING:

- Pola 'yang/pakaian/laundry/... apa ... bisa dijemput/diambil?' (boleh tanpa sdh/udh) = tanya status order siap diambil = STATUS

- Jika user bertanya 'atas nama [nama] udah/sudah?' = TRUE (tanya status laundry atas nama tertentu)

- Jika user memberitahu/konfirmasi 'sudah siap' / 'dah siap' / 'siapp' / 'udah siap kak?' = TRUE (tidak sekadar 'siap kak' / 'ok siap' pendek = PENUTUP)

FALSE jika: pilih ESTIMASI_SELESAI:

- Tanya KAPAN / JAM BERAPA siap/selesai/bisa diambil/dijemput (butuh estimasi waktu) = FALSE. Contoh: | kapan siap? | jam berapa siap? | siapnya kapan? | kira jam berapa bisa dijemput kak? |

- Negasi + bisa + (pagi/siang/sore/malam) ini (+ siap / jam angka) = tanya APAKAH BISA SELESAI di waktu itu = FALSE. Contoh: | Gk bisa sore ini siap kk | Ndak bisa sore ini kk jam 6 | gak bisa siap hari ini? |

FALSE jika: CRITICAL:

- Belum dapat notifikasi/nota via WA setelah sudah antar laundry = follow-up BON/NOTA digital = FALSE.

- 'Masih bisa siap gak?' / 'masih bisa siap?' + konteks mau bawa/antar laundry = FALSE.

- 'tutup tgl berapa?' / 'tutup tanggal brp?' = FALSE (tidak STATUS)

- atau yang menurut anda sangat yakin sebagai pertanyaan/pemberitahuan status laundry SAAT INI

=== ESTIMASI_SELESAI ===

User menanyakan ESTIMASI waktu selesai ATAU meminta SELESAI pada jam tertentu — tidak tanya apakah sudah siap sekarang, tidak minta kurir jemput/antar.

TRUE jika: empat sub-jenis (semuanya pilih ESTIMASI_SELESAI):

A) TANYA estimasi (jam berapa / kapan): | kapan siap? | jam berapa siap? | siapnya kapan? | kira jam berapa bisa dijemput? |

B) REQUEST selesai jam spesifik: | bisa siap jam 10? | minta selesai jam 14 | boleh siap jam 16.30 besok? | siap jam 11 mau ke medan |

C) TANYA bisa siap hari relatif (tanpa jam angka), termasuk typo: | bisa siap hari ini? | bisa siap besok? | bisa siap hari gak? | siap hari? |

D) Negasi + bisa + waktu relatif (makna: APAKAH BISA selesai?): | Gk bisa sore ini siap kk | Ndak bisa sore ini kk jam 6 | gak bisa siap hari ini? |

Untuk (B) WAJIB ada angka jam (10 / 14.30), tidak 'jam berapa'.

CRITICAL - bedakan dari STATUS:

- 'sudah siap kak?' / 'udah siap?' / 'bisa diambil?' (tanpa kapan/jam berapa dan tanpa jam angka) = FALSE.

- 'Gk bisa sore ini siap kk' / 'Ndak bisa sore ini jam 6' = TRUE (tanya bisa selesai di waktu itu), FALSE meski ada kata 'siap kk'.

CRITICAL - bedakan dari MINTA_JEMPUT_ANTAR:

- 'kira jam berapa bisa dijemput' = TRUE.

- 'bisa siap hari ini/besok/hari gak' = TRUE, tidak minta shareloc/kurir.

- 'tolong jemput di kamar 212' = FALSE.

CRITICAL - bedakan dari JAM_OPERASIONAL:

- 'jam berapa buka/tutup?' = FALSE.

CRITICAL - bedakan dari AMBIL_LEWAT_TUTUP:

- Customer mau AMBIL SENDIRI order yang sudah selesai LEWAT/SETELAH jam tutup = FALSE.

- Contoh AMBIL_LEWAT_TUTUP: | nanti jam 9 malam ambil ya | bisa ambil setelah tutup? |

=== AMBIL_LEWAT_TUTUP ===

User akan MENGAMBIL/JEMPUT SENDIRI laundry ke outlet, dan perkiraan datang di / LEWAT jam tutup (nunggu petugas setelah tutup), ATAU minta petugas TUNGGU karena mau ambil sendiri.

TRUE jika: - | nanti jam 9 malam saya ambil | bisa ambil setelah tutup? | jemput sendiri lewat jam tutup boleh? |

- | nunggu tutup ya kak saya ambil | bisa nunggu saya ambil jam 21.30? |

- | mau ambil jam 22 | nanti malem ambil ya lewat dikit |

- CRITICAL TRUE: | bs tunggu kak, mau ambil baju | bisa tunggu kak mau ambil | nunggu ya kak saya ambil baju | = minta petugas tunggu, customer AMBIL SENDIRI = TRUE (FALSE).

FALSE jika: Di luar jam operasional toko = sistem pakai JAM_TUTUP, tidak intent ini.

- Tanya KAPAN laundry siap / estimasi selesai = FALSE.

- Minta KURIR antar/jemput ke alamat = FALSE.

- Tanya masih buka? / jam berapa tutup? = FALSE.

- User ambil di jam operasional biasa tanpa sebut lewat tutup / tanpa minta tunggu = tidak intent ini.

- Order BELUM selesai: tetap bisa TRUE jika user jelas minta ambil lewat tutup — sistem cek order selesai di backend.

=== HARGA ===

User menanyakan harga/biaya laundry PER ITEM atau PER KILO, seperti:

| berapa harga? | berapa biaya? | harga berapa? | biaya berapa? |

| berapa harga baju? | berapa harga celana? | berapa harga handuk? | berapa harga boneka? | berapa harga sepatu? | berapa harga selimut? | berapa harga jaket? |

| berapa 1 boneka kak? | berapa 2 baju? | boneka brp? | cuci boneka berapa? |

| berapa harga per kilo? | berapa biaya per kg? | harga per kilo berapa? |

| minta pricelist | boleh dibantu pricelistnya kak? | kirim daftar harga | list harga dong | price list ya |

PENTING: 

- 'berapa kilo itu?' / 'brp kilo kak?' TANPA kata harga/biaya = tanya berat order = FALSE.

- Jika user bertanya 'berapa' + (harga/biaya) + (item laundry atau per kilo) = HARGA

- Jika user bertanya 'berapa' + angka + item (boneka, baju, dll) = TRUE (harga per item), FALSE total order

- TRUE jika: Meminta PRICELIST / PRICE LIST / DAFTAR HARGA / LIST HARGA (bahasa Inggris atau Indonesia) = minta daftar tarif laundry = TRUE. Contoh: | boleh dibantu pricelistnya kak | minta pricelist | share price list | daftar harga dong |

- TRUE jika: Tanya ONGKOS/ONGKIR sekaligus DURASI proses (1/2/3 hari, sehari, satu hari, dll.) di awal atau akhir kalimat = tanya TARIF layanan sesuai SLA di data harga = TRUE. Contoh: | Klu 1 hari brp ongkos nya | kalau 2 hari berapa ongkos | sehari brp ongkir kak | = TRUE.

- TRUE jika: Tanya ONGKOS/ONGKIR + JENIS LAYANAN (regular, ekspres, express, kilat) = tarif varian layanan di data harga = TRUE. Contoh: | berapa ongkos regular? | ongkos kilat brp? | brp ongkos ekspres | = TRUE.

- Jika user bertanya 'berapa' + (ongkir/ongkos/biaya antar/biaya jemput) TANPA durasi hari DAN TANPA sebut regular/ekspres/kilat (murni ongkos antar/jemput kurir) = MINTA_JEMPUT_ANTAR

- Jika user bertanya 'berapa' + (berat saja tanpa item) = bisa NOTA/TAGIHAN, bedakan konteks

FALSE jika: harga barang tambahan/ritel (tidak tarif cuci/setrika laundry):

- Jika pesan menyebut parfum / plastik (kantong plastik) / pewangi / hanger / tissue / barang kemasan = FALSE. Contoh: | berapa harga parfum kak? | berapa harga plastik? | sekarang berapa harga parfum? | harga kantong plastik? |

- Intent khusus harga barang itu nanti terpisah; untuk sekarang jawab FALSE agar CS yang tangani.

atau yang menurut anda sangat yakin sebagai pertanyaan harga/biaya laundry PER ITEM atau PER KILO (tidak barang tambahan di atas)

=== HARGA_PAKET_D ===

User menanyakan HARGA PAKET/MEMBER/LANGGANAN/DEPOSIT yang INCLUDE layanan ANTAR-JEMPUT (delivery).

TRUE jika: HARUS ADA kata paket/member/langganan/deposit DAN konteks antar/jemput/delivery:

| berapa harga paket yang include antar jemput? | harga member pakai antar? | paket bulanan sama ongkir? |

| daftar harga paket + antar jemput | paket member antar jemput | harga paket laundry sekalian antar jemput |

| paket delivery berapa? | harga deposit member + jemput |

FALSE jika: Tanya harga paket/member TANPA menyebut antar/jemput/ongkir paket/delivery = FALSE (tidak HARGA_PAKET_D).

- Contoh FALSE: | berapa harga paket? | harga member? | ada paket bulanan? | paket setrika aja berapa? |

- Instruksi kurir saja tanpa tanya harga paket: | tolong dijemput | minta antar baju kamar 212 | = FALSE.

- 'Setrika aja' tanpa kata paket/member = FALSE.

PENTING:

- Ini pertanyaan TARIF paket varian include antar-jemput, tidak permintaan kurir jemput order sekarang.

- Jika user bertanya harga PER ITEM atau PER KILO (baju, celana, per kg) = FALSE (tidak HARGA_PAKET_D)

=== HARGA_PAKET ===

User menanyakan HARGA PAKET, HARGA MEMBER, HARGA PAKET BULANAN, HARGA DEPOSIT MEMBER, atau HARGA DEPOSIT PAKET laundry.

TRUE jika: HARUS ADA kata paket/member/langganan/deposit:

| berapa harga paket? | harga paket berapa? | paket laundry berapa? | daftar harga paket? |

| berapa harga member? | harga member berapa? | biaya jadi member? |

| berapa harga paket bulanan? | paket bulanan berapa? | langganan bulanan berapa? | harga langganan? |

| paket cuci bulanan? | member paket berapa? |

| berapa harga deposit member? | harga deposit member berapa? | deposit member berapa? |

| berapa harga deposit paket? | harga deposit paket berapa? | deposit paket berapa? |

| paket setrika aja berapa? | paket bulanan setrika? | paket member setrika? | ada paket bulanan? setrika aja | paket cuci setrika berapa? |

FALSE jika: 'Setrika aja' / 'Setrika aj' / 'Setrika saja' TANPA kata paket/member/langganan = FALSE. Ini bisa instruksi treatment atau pernyataan lain.

- Kalimat pernyataan/instruksi TANPA tanya harga + TANPA kata paket/member/langganan/deposit = FALSE.

- Contoh FALSE: | Setrika aja | Denosa Setrika aja loh | Setrika aj | setrika aja kak | ntr biar kami strika aja | nanti biar kami setrika aja |

- HARGA_PAKET HANYA jika user BERTANYA dengan kata paket/member/langganan, misal: paket setrika aja, paket bulanan setrika, paket member setrika.

PENTING:

- Jika user bertanya harga PER ITEM atau PER KILO (baju, celana, per kg) = FALSE (tidak HARGA_PAKET)

CRITICAL - FALSE (tidak HARGA_PAKET — pakai HARGA_PAKET_D):

- User bertanya harga paket/member/langganan/deposit sekaligus antar/jemput/ongkir paket/delivery/include antar = FALSE.

- Contoh: | berapa harga paket yang include antar jemput? | harga member pakai antar? | paket member antar jemput | paket bulanan sama ongkir? | daftar harga paket + antar jemput |

=== PERMINTAAN ===

User melakukan PERMINTAAN KHUSUS atau INSTRUKSI KHUSUS terkait laundry.

TRUE jika:

- Permintaan treatment khusus: | bantu dibersihkan | tolong difokusin | baju ini dicuci khusus | noda ini dihilangkan |

- Permintaan waktu/prioritas: | tolong dipercepat | didulukan ya | kapan bisa selesai | prioritas dong |

- Permintaan cara treatment (untuk order yang sudah ada): | ganti parfum | jangan pakai pelembut | lipat rapi | setrika aja (instruksi untuk baju yang sudah di laundry) | ntr biar kami strika aja |

- Konfirmasi ambil sendiri: | saya jemput nanti | aku ambil sendiri | nanti sore saya datang |

- Ada kata: bantu/bntu (typo)/tolong/minta/bisa + object laundry (baju/celana/handuk/kantong/dll)

- CRITICAL: Minta SALAH SATU pakaian/item tertentu diambil/dulukan lebih dulu dari order yang sudah ada (belum waktunya ambil semua): | bisa ga baju dinas diambil dulu? | tolong seragam coklat didulukan | kemarin ada laundry, baju X di ambil dulu | = TRUE (tidak minta kurir jemput/antar dari alamat).

FALSE jika (FALSE - ini HARGA_PAKET):

- User TANYA harga paket/member/langganan + spesifikasi layanan: | ada paket bulanan? setrika aja | paket setrika aja berapa? | ada paket cuci setrika? | paket bulanan cuci setrika? |

- 'Setrika aja' / 'cuci setrika' setelah tanya paket = spesifikasi JENIS paket yang ditanya, tidak instruksi treatment = HARGA_PAKET

FALSE jika: Hanya sapaan: 'halo' tanpa permintaan

- Hanya tanya status: 'kapan siap?' tanpa instruksi khusus

- Minta kurir jemput/antar ke alamat (kamar/hotel/jemput laundry dari rumah): itu MINTA_JEMPUT_ANTAR

- Pemberitahuan singkat: 'Mau jemput,jgn tutup dlu' (terlalu singkat/informal)

=== MINTA_JEMPUT_ANTAR ===

User MEMINTA KURIR/LAUNDRY untuk datang JEMPUT atau ANTAR (tidak sekadar menanyakan ongkir/ongkos).

TRUE (MINTA JEMPUT/ANTAR) - HARUS ADA kata permintaan atau pertanyaan atau instruksi jemput ke lokasi:

- Minta jemput ATAU antar (beda: jemput = ambil dari lokasi customer, antar = antar ke lokasi customer).

- Typo/singkatan 'anter' / 'antr' / 'dianter' / 'diantr' = sama dengan antar.

- CRITICAL TRUE: 'bs antr gak baju kami?' / 'bs antr?' / 'bisa antr?' = MINTA_JEMPUT_ANTAR jenis ANTAR.

- 'besok anter laundry ya' / 'besok antar londry ya' = TRUE (permintaan antar sameday).

- CRITICAL TRUE: 'kk besok pagi bisa ambil kain kotor' / 'ambil kain kotor' / 'ambil baju kotor' = JEMPUT (kurir ambil cucian kotor), tidak status, tidak customer ambil sendiri.

- CRITICAL TRUE: 'sekalian bawakan kain yang udah siap' / 'bawak kan kain yg siap' = ANTAR (kurir antar cucian siap).

- Jika customer minta antar SEKALIGUS jemput (atau sebaliknya, 'jemput juga' / ambil kotor + bawakan yang siap) = tetap MINTA_JEMPUT_ANTAR, jenis = antar.

- Follow-up session (konfirmasi lokasi/tarif/jam/setuju/instant) tetap MINTA_JEMPUT_ANTAR.

HARUS ADA kata permintaan atau pertanyaan atau instruksi jemput ke lokasi:

- Kata kunci: tolong/minta/bisa/boleh/dong/kapan/berapa + jemput/antar/anter/antr

- tolong jemput, minta dijemput, bisa diantar?, boleh dijemput?, kapan diantar?

- jam brp bsk diantarnya?, jam berapa besok diantar?, kapan diantarnya? = tanya jadwal pengantaran order = MINTA_JEMPUT_ANTAR

- bisa jemput kak?, nanti bisa jemput kak?, jemput dong, antar ya dong

- Singkatan: bs jmpt baju?, bs jemput?, bs antr gak baju? = sama dengan bisa jemput/antar = MINTA_JEMPUT_ANTAR

- katanya mau antar, katanya mau jemput = relay/konfirmasi permintaan antar = MINTA_JEMPUT_ANTAR

FALSE jika: SEMUA pertanyaan ongkir/ongkos antar-jemput = FALSE (CS), FALSE. Contoh: | brp ongkirnya? | berapa ongkosnya? | brp ong nya kak? | biaya antar? | udah sm ongkir ni kak? | sudah sama ongkir? | udah termasuk ongkir? | ongkir brp? | — kecuali ada durasi hari atau regular/ekspres/kilat (itu HARGA).

- CRITICAL: Instruksi ambil/jemput baju/laundry/kain/bedcover/sprei dari LOKASI (kamar hotel/kost, depan kamar, rumah sakit, sekolah, alamat/jalan) = TRUE. Contoh: | kk ambil baju kotor sama bedcover di depan kamar 212 | ambil laundry di hotel | jemput di kamar 305 | ambil di RS |

FALSE jika: Minta SATU jenis pakaian/item (baju dinas, seragam, dll.) diambil/dulukan DULU dari order/cucian yang sudah di laundry — prioritas item, tidak minta kurir jemput-antar = FALSE.

FALSE (tidak MINTA JEMPUT/ANTAR) - SANGAT PENTING:

- Tanya STATUS order (sudah/sdh/udh bisa dijemput/diambil) termasuk per kategori: | pakaian harian apa sdh bisa dijemput? | yang cuci setrika udh bisa dijemput? | = FALSE, tidak minta kurir.

- Tanya ESTIMASI selesai / kapan siap diambil: | kapan siap? | jam berapa siap? | kira jam berapa bisa dijemput kak? | jam berapa bisa dijemput? | kapan bisa diambil? | = FALSE, tidak minta kurir.

- User yang akan MENGAMBIL SENDIRI: | mau jemput | saya jemput | aku ambil | awak jemput | nanti saya jemput |

- Konfirmasi/Pemberitahuan: | baik nanti dijemput | ok sore diantar | iya nanti akan dijemput | siap dijemput |

- Hanya memberitahu jadwal tanpa permintaan eksplisit: | nanti sore dijemput | jam 2 dijemput | — TAPI 'besok antar/anter laundry ya' = TRUE (permintaan).

- 'bisa diambil?' / 'udah bisa diambil?' TANPA 'kain kotor' = FALSE (tidak jemput kurir).

- CRITICAL: 'masih bisa antar laundry?' / 'masih bisa antar?' = tanya AVAILABILITAS operasional = FALSE (tidak MINTA_JEMPUT_ANTAR). Kata 'masih' membedakan: tanya masih buka/bisa vs permintaan.

- CRITICAL: 'kira jam berapa bisa dijemput' / 'jam berapa bisa dijemput' (tanpa lokasi kamar/hotel) = tanya kapan order SIAP diambil = FALSE.

- CRITICAL: Customer mau AMBIL SENDIRI lewat/setelah jam tutup = FALSE.

- CRITICAL: 'Mau jemput' / 'saya jemput nanti' = User SENDIRI yang akan mengambil = FALSE (bedakan dari 'ambil baju di kamar X' = minta kurir)

- CRITICAL: Instruksi 'ambil/jemput' + lokasi (kamar/hotel/RS/sekolah/alamat) = MINTA_JEMPUT_ANTAR walaupun TANPA kata tolong/minta/bisa — itu permintaan kurir ke alamat.

FALSE jika: Pertanyaan HARGA PAKET/MEMBER/DEPOSIT + antar/jemput/ongkir paket/delivery (berapa harga paket include antar? harga member pakai antar? paket member antar jemput) = FALSE.

FALSE jika: Tanya ongkos/ongkir + durasi hari (1/2/3 hari, sehari) ATAU + regular/ekspres/kilat = FALSE (tarif layanan), FALSE.

FALSE jika: Kalimat hipotetis/kondisional 'kalau/klo/kalo' lalu antar/jemput = tidak minta kurir. Contoh: | Kalau express di antar sekarang | kalau jemput brp? | klo diantar ekspres |

FALSE jika: Customer yang antar/jemput SENDIRI = tidak minta kurir. Contoh: | kami antar | kami jemput | saya antar | aku jemput | kami aja yang antar | saya yang antar | aku aja jemput |

- Contoh FALSE: 'Mau jemput,jgn tutup dlu' (user akan ambil sendiri, tidak minta kurir)

- Contoh FALSE: 'Baikla nnti sore dijemput ya kak' (ini konfirmasi)

- Contoh FALSE: 'Ok dijemput ya' (ini konfirmasi)

- Contoh FALSE: 'Saya jemput nanti' (user akan ambil sendiri)

=== LOKASI ===

User mengirim PIN/SHARELOC/link Google Maps, ATAU menjelaskan detail alamat/titik lokasi TANPA meminta kurir jemput/antar.

TRUE jika: - Shareloc / pin WhatsApp / link maps.app.goo.gl / koordinat lat,lng.

- Menjelaskan detail alamat dalam satu kalimat: rumah pagar kuning, kos Azzahra kamar 2, mess BPK, toko/warung/studio/kedai sebelah Indomaret, ini alamatnya…

- Sistem mengisi kategori nama (Rumah/Kos/Mess/Toko/…) dari detail — user TIDAK perlu dipilihkan rumah/kos dulu.

FALSE:

- Minta jemput/antar/kurir/ongkir antar = FALSE.

- Tanya status/estimasi order = STATUS / ESTIMASI_SELESAI.

- Hanya sapaan tanpa alamat = FALSE.

=== JAM_OPERASIONAL ===

User menanyakan jam operasional TOKO (buka/tutup) ATAU batas terima laundry ATAU jadwal libur.

DUA JENIS (keduanya JAM_OPERASIONAL, sistem bedakan jawaban): (A) 'masih buka?' = jawab 'masih buka kak/bang'. (B) 'masih bisa?' / 'masih bisa terima kain?' / 'kapan jadwal terakhir penerimaan?' = jawab konfirmasi ke petugas dulu.

| jam berapa buka? | jam berapa tutup? | kapan tutup? | masih buka? | masih bukak? | buka buk? | buka kak mau ambil baju? | sudah tutup? | jam operasional? | jam buka berapa? | sampai jam berapa laundry buka hari ini? | kak bukak lagi kapan? | kapan buka lagi? |

| liburnya kapan? | kapan libur? | hari libur apa? | libur hari apa? | tutup tgl berapa? | tutup tanggal brp? | libur tgl berapa? |

| besok pagi buka jam berapa? | besok buka jam brp ya? | nanti sore buka jam berapa? | untuk besok pagi buka jam brp ya? |

| kapan terakhir terima kain? | kapan terakhir terima laundry? | terakhir terima jam berapa? | kapan jadwal terakhir penerimaan? | jadwal terakhir penerimaan? |

| masih terima kain kak? | masih terima baju? | masih terima laundry? | masih terima kak? |

| masih bisa antar laundry? | masih bisa antar? | masih bisa jemput? | = tanya AVAILABILITAS (masih buka/bisa layani) = JAM_OPERASIONAL

| masih bisa siap gak kk mau bawa sprei? | masih bisa siap mau antar? | = user BELUM antar laundry, tanya apakah masih terima/proses = JAM_OPERASIONAL

| Ka londry buka ga? | laundry buka gak? | loundry masih buka ga kak? | buka ga sekarang? (dengan konteks laundry/toko) = tanya apakah outlet MASIH BUKA = TRUE (tidak pertanyaan umum / tidak kategori lain)

TRUE jika: PENTING:

- JIKA user tanya apakah laundry/toko/outlet BUKA atau TIDAK / buka ga / buka gak / masih buka = TRUE (walaupun ada tanda ? atau typo londry/loundry).

- JIKA ada kata BUKA/TUTUP/OPERASIONAL dalam konteks jam = JAM_OPERASIONAL

- JIKA user tanya 'kapan terakhir terima' / 'terakhir terima jam berapa' = tanya batas waktu terima laundry = JAM_OPERASIONAL

- JIKA user tanya 'masih terima kain/baju/laundry' = tanya apakah masih buka terima = JAM_OPERASIONAL

- JIKA user tanya 'masih bisa antar/jemput' = tanya availabilitas operasional (masih buka layanan antar?) = TRUE. Kata 'masih' membedakan dari permintaan.

- JIKA user tanya 'liburnya kapan?' / 'kapan libur?' / 'hari libur?' / 'tutup tgl berapa?' / 'tutup tanggal brp?' = tanya jadwal tutup/libur toko = JAM_OPERASIONAL

- JIKA user tanya 'masih bisa siap?' + konteks mau bawa/antar laundry = user BELUM antar, tanya availabilitas (masih terima/proses?) = TRUE. tidak STATUS (status = tanya order yang SUDAH ada).

FALSE jika: PENTING:

- 'Jam berapa?' / 'Jam brp?' TANPA kata buka/tutup/operasional/terima sama sekali = user menanya WAKTU SAAT INI = FALSE

- 'jam berapa bisa jemput?' / 'kira jam berapa bisa dijemput?' = tanya estimasi kapan order siap diambil = FALSE (tidak JAM_OPERASIONAL, tidak MINTA_JEMPUT kecuali ada lokasi kamar/hotel)

- 'bisa ambil setelah tutup?' / 'nanti jam 9 malam ambil' = FALSE (ambil sendiri lewat tutup), FALSE

- 'jam brp bsk diantarnya?' / 'jam berapa besok diantar?' / 'kapan diantarnya?' = tanya jadwal PENGANTARAN order = FALSE (tidak JAM_OPERASIONAL)

- 'bisa antar laundry?' / 'tolong antar laundry' / 'bs jmpt baju?' (TANPA 'masih') = permintaan jemput/antar = MINTA_JEMPUT_ANTAR

- Contoh FALSE: 'jam berapa?', 'jam brp kak?' (tanpa buka/tutup) | 'jam berapa diantar?', 'kapan dijemput?', 'jam brp bsk diantarnya?'
