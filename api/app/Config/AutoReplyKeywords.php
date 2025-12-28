<?php 
return [  
    'NOTA' => [
        'max_length' => 100,
        'case' => null,
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
        'ai_prompt' => "User meminta:\n
        | bon | nota | struk | tagihan | bukti pembayaran laundry |\n
        atau yang menurut anda sangat yakin sebagai permintaan nota/bon"
    ],

    'STATUS' => [
        'max_length' => 100,
        'case' => null,
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
        'ai_prompt' => 'User minta jemput/antar atau menanyakan kapan dijemput/antar'
    ],

    'PERMINTAAN' => [
        'max_length' => 100,
        'case' => 3,
        'auto_reply' => false,
        'patterns' => [
            '/(bi*sa*|bo*le*h).*(sa*ya*|a*ku|ka*mi).*(di)?(ambi*l|je*m*pu*t)/i',
        ],
        'ai_prompt' => 'User melakukan permintaan apapun SELAIN jemput/antar laundry'
    ],

    'JAM_OPERASIONAL' => [
        'max_length' => 30,
        'priority' => null,
        'auto_reply' => true,
        'patterns' => [
            '/(ka*pa*n|ma*si*h)\s*\b(bu*ka*|tu*tu*p)/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(bu*ka*|tu*tu*p)/i',
        ],
        'ai_prompt' => 'User butuh informasi tentang jam buka/tutup atau menanyakan kapan buka/tutup atau menanyakan masih buka/tutup atau menanyakan sudah buka/tutup, hanya menanyakan tentang buka atau tutup. BUKAN kapan atau jam berapa bisa dijemput/antar'
    ],
];
