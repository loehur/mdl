<?php
/** One-off: php generate-png.php — buat j-icon-192.png & j-icon-512.png dari desain MDL */
if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension required\n");
    exit(1);
}

function drawIcon($size)
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $bg = imagecolorallocate($img, 11, 61, 58);
    $white = imagecolorallocate($img, 255, 255, 255);
    $radius = (int) round($size * 0.1875);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    $s = $size / 512;
    $stroke = max(4, (int) round(28 * $s));

    // Neck line
    imageline($img, (int) (256 * $s), (int) (112 * $s), (int) (256 * $s), (int) (176 * $s), $white);

    // Shoulders + body (simplified filled shirt)
    $points = [
        (int) (128 * $s), (int) (176 * $s),
        (int) (176 * $s), (int) (176 * $s),
        (int) (212 * $s), (int) (272 * $s),
        (int) (212 * $s), (int) (416 * $s),
        (int) (300 * $s), (int) (416 * $s),
        (int) (300 * $s), (int) (272 * $s),
        (int) (336 * $s), (int) (176 * $s),
        (int) (384 * $s), (int) (176 * $s),
        (int) (348 * $s), (int) (272 * $s),
        (int) (348 * $s), (int) (416 * $s),
        (int) (164 * $s), (int) (416 * $s),
        (int) (164 * $s), (int) (272 * $s),
    ];
    imagefilledpolygon($img, $points, $white);

    // Cut neck hole
    $neckBg = imagecolorallocate($img, 11, 61, 58);
    imagefilledellipse($img, (int) (256 * $s), (int) (168 * $s), (int) (120 * $s), (int) (72 * $s), $neckBg);
    imageellipse($img, (int) (256 * $s), (int) (168 * $s), (int) (120 * $s), (int) (72 * $s), $white);

    imagesetthickness($img, $stroke);
    imagerectangle($img, 0, 0, $size - 1, $size - 1, $bg);

    return $img;
}

foreach ([192, 512] as $size) {
    $img = drawIcon($size);
    $path = __DIR__ . '/j-icon-' . $size . '.png';
    imagepng($img, $path);
    imagedestroy($img);
    echo "Wrote {$path}\n";
}
