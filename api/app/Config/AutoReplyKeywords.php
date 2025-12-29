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
        'ai_prompt' => "User menanyakan status progress laundry seperti:\n
        | sudah selesai? | bisa diambil? | kapan siap? | sudah jadi? | sudah bisa dijemput/antar? | sudah bisa diambil? |\n
        atau yang menurut anda sangat yakin sebagai pertanyaan status progress laundry"
    ],

    'MINTA_JEMPUT_ANTAR' => [
        'max_length' => 100,
        'case' => 2,
        'auto_reply' => false,
        'patterns' => [
            '/^\s*(je*m*pu*t|anta*r)\s*$/i',
        ],
        'ai_prompt' => 'HANYA JIKA User meminta pihak laundry (KURIR) untuk datang MENJEMPUT pakaian kotor atau MENGANTAR pakaian bersih. PENTING: JANGAN pilih ini jika USER mengatakan dia sendiri yang akan menjemput/mengambil (misal: "saya jemput", "ku jemput", "nanti saya ambil").'
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
        'ai_prompt' => 'User butuh informasi tentang jam buka/tutup atau menanyakan kapan buka/tutup atau menanyakan masih buka/tutup atau menanyakan sudah buka/tutup, hanya menanyakan tentang buka atau tutup. BUKAN kapan atau jam berapa bisa dijemput/antar'
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
];
