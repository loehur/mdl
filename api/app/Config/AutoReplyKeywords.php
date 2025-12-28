<?php 
return [  
    'PEMBUKA' => [
        'max_length' => 20,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(p|ping|ka*k|ba*n*g|b*a*pa*k|i*bu*k*|a*de*k|he*a*l+o|as+a*l+a*mu*a*l+a*i*ku*m|tes)\s*$/i',
            '/(pa*gi|so*re|si*a*ng|ma*la*m|ha*e*l+o+)\s*\b(ba*n*g|ka*k|pa*k|i*bu*k*|a*de*k*|a*na*k)/i',
        ],
        'ai_prompt' => 'Salam pembuka, sapaan awal (contoh: halo/hai/ping/pagi/siang/malam/sore/kak/bang/pak/bu)'
    ],

    'NOTA' => [
        'max_length' => 100,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(bon|nota+|stru*k|bil+|ta*gi*ha*n|re*si)\s*$/i',
            '/ata*s\s*na*ma*/i',
            '/(be*lu*m)\s*di*(te*ri*ma*|ki*ri*m)/i',
            '/(be*ra*pa*|ki*ri*m|cek)\s*\b(to*ta*l|ju*mla*h|bon|stru*k|bil+|ta*gi*ha*n|re*si)/i',
            '/(be*ra*pa*|cek)\s*\b(to*ta*l|ju*mla*h|kg|be*ra*t|ti*mba*nga*n*)/i',
            '/(to*ta*l|ju*mla*h)\s*\b(la*o*u*ndry*i*)/i',
            '/(bon|nota*|bil+|ta*gi*ha*n|re*si|bu*kti*)\s*\b(ke*tik|mi*nta|ki*ri*m|ba*gi*|cek|ma*na*|a*da*|pe*rlu|bu*tu*h)/i',
            '/(ke*tik|mi*nta|ki*ri*m|ba*gi*|cek|ma*na*|a*da*|pe*rlu|bu*tu*h)\s*\b(bon|nota*|bil+|ta*gi*ha*n|re*si|bu*kti*)/i'
        ],
        'ai_prompt' => 'User meminta bon/nota/struk/tagihan/bukti pembayaran laundry'
    ],

    'STATUS' => [
        'max_length' => 100,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
            '/((s*u*)?da*h*|ka*pa*n)\s*\b(si+a+p|be*re*s|ke*la*r|se*ls*e*s*a*i*|re*a*dy*i*|j*adi*)/i',
            '/(si+a+p|be*re*s|ke*la*r|se*ls*e*s*a*i*|re*a*dy*i*|j*adi*)\s*\b(ka*pa*n|be*lu*m)/i',
            '/((s*u*)?da*h*|ka*pa*n)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
            '/(ka*pa*n)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
            '/(ka*pa*n)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(siap|se*le*sa*i*|ke*la*r|be*re*s)/i',
        ],
        'ai_prompt' => 'User menanyakan status/progress laundry (sudah selesai? bisa diambil? kapan siap? sudah jadi?)'
    ],

    'PERMINTAAN' => [
        'max_length' => 100,
        'priority' => 3,
        'auto_reply' => false,
        'patterns' => [
            '/(bi*sa*|bo*le*h).*(sa*ya*|a*ku|ka*mi).*(di)?(ambi*l|je*m*pu*t)/i',
        ],
        'ai_prompt' => 'User melakukan permintaan selain jemput/antar laundry'
    ],

    'MINTA_JEMPUT_ANTAR' => [
        'max_length' => 100,
        'priority' => 2,
        'auto_reply' => true,
        'patterns' => [
            '/^\s*(je*m*pu*t|anta*r)\s*$/i',
            '/(bi*sa*|bo*le*h|to*lo*ng)\s*\b(je*m*pu*t|anta*r|ki*ri*m)/i',
            '/(je*m*pu*t|anta*r)\s*\b(la*o*u*ndry*i*|ba*ju)/i',
            '/(to*lo*n*g)\s*\b(la*o*u*ndry*i*|ba*ju)\s*\b(je*m*pu*t|anta*r|ki*ri*m)/i'
        ],
        'ai_prompt' => 'User melakukan permintaan jemput/antar laundry (harus ada kata permintaan seperti: minta, tolong, bisa, boleh, bantu, dll)'
    ],

    'CEK_BUKA' => [
        'max_length' => 30,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/(ka*pa*n|ma*si*h)\s*\b(bu*ka*|tu*tu*p)/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(bu*ka*|tu*tu*p)/i',
        ],
        'ai_prompt' => 'User meminta informasi jam/kapan buka/tutup, masih buka atau sudah tutup. tidak termasuk jam/kapan di antar/jemput'
    ],

    'PENUTUP' => [
        'max_length' => 20,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/\bma*ka*(s|c)(i|e)*h\b/i',
            '/\bte*ri*ma*ka*si*h\b/i',
            '/\btha*nks\b/i',
            '/\b(thx|tq|ty|ok)\b/i',
            '/((hm+|ok(e*)?|sip)\s*)*(y(a*)?\s*)?(u*da*h|s*u*da*h|la+h)/i',
            '/(oh*)\s*(gi*tu+)/i',
            '/(ok|oh).*(siap|sip|ok)/i',
        ],
        'ai_prompt' => 'Penutup percakapan, konfirmasi, terima kasih (contoh: ok, oke, sip, siap, makasih, terima kasih, thanks, sudah, lah, iya, thx), atau hanya memberitahu kalau (sudah bayar)/(sudah lunas)/(sudah diambil)/(akan menjemput)/(akan mengantarkan)'
    ],

    'EMOTE' => [
        'max_length' => 20,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            // Emoji patterns (Unicode ranges for common emojis)
            '/[\x{1F600}-\x{1F64F}]/u', // Emoticons (😀-🙏)
            '/[\x{1F300}-\x{1F5FF}]/u', // Symbols & Pictographs
            '/[\x{1F680}-\x{1F6FF}]/u', // Transport & Map
            '/[\x{1F900}-\x{1F9FF}]/u', // Supplemental Symbols
            '/[\x{2600}-\x{26FF}]/u',   // Miscellaneous Symbols (☀-⛿)
            '/[\x{2700}-\x{27BF}]/u',   // Dingbats
            '/[\x{1F1E0}-\x{1F1FF}]/u', // Flags
            '/[\x{1F910}-\x{1F96B}]/u', // Additional emoticons
            '/[\x{1F980}-\x{1F9E0}]/u', // Additional symbols
            
            // Text-based emoticons
            '/^(:\)|:\(|:D|:P|;-?\)|<3|:\*|:\"\(|:-?\)|:-?D)$/i',
            
            // Very short responses (1-3 chars, likely just emoji or simple acknowledgment)
            '/^\s*[👍👌✌️🙏❤️😊😁😂🤣😍🥰😘😎🤗🙌💪👏🤝✨🔥💯🎉🎊]\s*$/u',
        ],
        'ai_prompt' => 'Hanya emoji/emote atau candaan tawa seperti hehe, haha, wkwk. tidak termasuk simbol2 yang tidak membentuk emote/emoji.'
    ]
];
