<?php
$tests = ['mksh kk', 'mksh', 'mksh byk kak', 'terima kasih kk'];
$pattern = '/\b('
    . 'makasi|'
    . 'ma*ka*(s|c)(i|e)+h?|'
    . 'te*ri*ma*ka*si*h|'
    . '(trima|terima)\s+(kasih|ksih|ksh)|'
    . 'trima*kasih|trimakasih|trmksh|trm\s*ksh|mksh|'
    . 'tha*nks|thx|tq|ty'
    . ')\b/iu';

foreach ($tests as $text) {
    $t = strtolower(trim($text));
    echo $text . ' => ' . (preg_match($pattern, $t) ? 'MATCH' : 'NO') . ' len=' . mb_strlen($t) . PHP_EOL;
}

// strict allowlist ack for mksh kk
$okTok = 'okk*(?:e+y*|ey+)?';
$ack = '/^\s*\b(' . $okTok . '|baik+|sip+|sia+p+|gpp|gak\s*apa\s*apa|ga\s*apa\s*apa|iya+|ya+)'
    . '(\s+(deh|lah|dong|ya))*'
    . '(?:\s+(kak|kk|bang|min|mbak|pak|bu|buk|mas|om|dek|nte|penya|punya))*'
    . '\s*[.!?]*\s*$/iu';
echo 'ack only mksh kk => ' . (preg_match($ack, 'mksh kk') ? 'MATCH' : 'NO') . PHP_EOL;
