<?php 
/**
 * Auto Reply Keywords Configuration
 * Format: [handler_name => ['max_length' => int, 'keywords' => [...]]]
 * max_length: Maksimal panjang pesan (dalam karakter) untuk trigger handler ini
 * Set 0 untuk unlimited (tidak ada batasan panjang)
 */
return [  
    'PEMBUKA' => [
    'max_length' => 20,
        'patterns' => [
            '/^\s*(ping|ka*k|ba*n*g|b*a*pa*k|i*bu*k*|a*de*k|he*a*l+o|as+a*l+a*mu*a*l+a*i*ku*m|tes)\s*$/i',
            '/(pa*gi|so*re|si*a*ng|ma*la*m|ha*e*l+o+)\s*\b(ba*n*g|ka*k|pa*k|i*bu*k*|a*de*k*|a*na*k)/i',
        ]
    ],

    'NOTA' => [
        'max_length' => 100,
        'patterns' => [
            '/^\s*(bon|nota+|stru*k|bil+|ta*gi*ha*n|re*si)\s*$/i',
            '/ata*s\s*na*ma*/i',
            '/(ke*ti*k|mi*nta*|ki*ri*m|ba*gi*|cek|ma*na*)\s*\b(stru*k|nota+|bil|bon|ta*gi*ha*n|re*si|bu*kti*)/i',
            '/(be*lu*m)\s*\b(di*(te*ri*ma*|ki*ri*m))/i',
            '/(be*ra*pa*|ki*ri*m|cek)?\s*\b(to*ta*l|ju*mla*h)/i',
            '/(be*ra*pa*|cek)?\s*\b(to*ta*l|ju*mla*h|kg|be*ra*t|ti*mba*nga*n*)/i',
            '/(to*ta*l|ju*mla*h)\s*\b(la*o*u*ndry*i*)/i',
        ]
    ],

    'STATUS' => [
        'max_length' => 100,
        'patterns' => [
            '/^\s*(cek|sta*tu*s)\s*$/i',
            '/(s*u*da*h*|u*da*h*|da*h*|ka*pa*n)\s*\b(si+a+p|be*re*s|se*ls*e*s*a*i*|re*a*dy*i*)/i',
            '/(si+a+p|be*re*s|se*ls*e*s*a*i*|re*a*dy*i*)\s*\b(ka*pa*n|be*lu*m)/i',
            '/(s*u*da*h*|u*da*h*|da*h*)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
            '/(ka*pa*n)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
            '/(ka*pa*n)\s*\b(bi*sa*|bo*le*h|da*pa*t)\s*\b(di*(ambi*l|je*mpu*t))/i',
        ]
    ],

    'JAM_BUKA' => [
        'max_length' => 30,
        'patterns' => [
            '/(ka*pa*n)\s*\b(bu*ka*|tu*tu*p)/i',
            '/(ja*m)\s*\b(be*ra*pa*)\s*\b(bu*ka*|tu*tu*p)/i',
        ]
    ],

    'PENUTUP' => [
        'max_length' => 20,
        'patterns' => [
            '/\bma*ka*(s|c)(i|e)*h\b/i',
            '/\bte*ri*ma*ka*si*h\b/i',
            '/\btha*nks\b/i',
            '/\b(thx|tq|ty)\b/i',
        ]
    ]
];
