<?php
/*
 * Génère les dérivés du logo ANaTT (docs/logoannat.png, fond transparent) :
 *  - frontend/src/assets/logo-anatt.png      (logo complet, usage in-app)
 *  - frontend/src/assets/icon-anatt.png       (icône carrée verte + ANaTT blanc)
 *  - frontend/public/favicon-16|32|48.png, apple-touch-icon.png (180), favicon-512.png
 *  - frontend/public/favicon.ico              (ICO encapsulant un PNG 32x32)
 */

$src   = __DIR__ . '/logoannat.png';
$front = dirname(__DIR__) . '/frontend';
$assets = $front . '/src/assets';
$public = $front . '/public';

$im = imagecreatefrompng($src);
$w = imagesx($im); $h = imagesy($im);

function inkAt($im, $x, $y): bool {
    $c = imagecolorat($im, $x, $y);
    if ((($c >> 24) & 0x7F) > 40) return false;
    $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
    return ($r + $g + $b) / 3 < 130;
}

// ── Détection du wordmark "ANaTT" dans la bande supérieure ──
$band = (int) round($h * 0.48);
$col = [];
for ($x = 0; $x < $w; $x++) { $n = 0; for ($y = 0; $y < $band; $y++) if (inkAt($im, $x, $y)) $n++; $col[$x] = $n; }
$start = 0; for ($x = 0; $x < $w; $x++) if ($col[$x] > 0) { $start = $x; break; }
$gap = 0; $wordRight = $w - 1;
for ($x = $start; $x < $w; $x++) {
    if ($col[$x] === 0) { $gap++; if ($gap >= 12) { $wordRight = $x - $gap; break; } } else $gap = 0;
}
$top = $band; $bottom = 0;
for ($x = $start; $x <= $wordRight; $x++) for ($y = 0; $y < $band; $y++)
    if (inkAt($im, $x, $y)) { if ($y < $top) $top = $y; if ($y > $bottom) $bottom = $y; }

$cw = $wordRight - $start + 1; $ch = $bottom - $top + 1;
echo "wordmark ANaTT: x[$start..$wordRight] y[$top..$bottom] => {$cw}x{$ch}\n";

// Crop du wordmark, puis négatif -> ANaTT blanc sur fond transparent.
$mark = imagecreatetruecolor($cw, $ch);
imagealphablending($mark, false); imagesavealpha($mark, true);
imagefill($mark, 0, 0, imagecolorallocatealpha($mark, 0, 0, 0, 127));
imagecopy($mark, $im, 0, 0, $start, $top, $cw, $ch);
$markWhite = imagecreatetruecolor($cw, $ch);
imagealphablending($markWhite, false); imagesavealpha($markWhite, true);
imagefill($markWhite, 0, 0, imagecolorallocatealpha($markWhite, 0, 0, 0, 127));
imagecopy($markWhite, $mark, 0, 0, 0, 0, $cw, $ch);
imagefilter($markWhite, IMG_FILTER_NEGATE); // noir -> blanc, transparence conservée

// Wordmark "ANaTT" autonome (couleur + blanc) — utilisé en variante compacte (sidebar).
imagesavealpha($mark, true);
imagepng($mark, $assets . '/logo-anatt-wordmark.png');
echo "wordmark couleur -> src/assets/logo-anatt-wordmark.png\n";
imagesavealpha($markWhite, true);
imagepng($markWhite, $assets . '/logo-anatt-wordmark-white.png');
echo "wordmark blanc -> src/assets/logo-anatt-wordmark-white.png\n";

// ── Icône carrée : fond vert ANaTT (#1D9E4F) + ANaTT blanc centré ──
function makeIcon($markWhite, $cw, $ch, $size): \GdImage {
    $icon = imagecreatetruecolor($size, $size);
    imagealphablending($icon, false); imagesavealpha($icon, true);
    imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
    imagealphablending($icon, true);
    // Fond vert (carré plein ; iOS arrondit l'apple-touch-icon automatiquement).
    $green = imagecolorallocate($icon, 0x1D, 0x9E, 0x4F);
    imagefilledrectangle($icon, 0, 0, $size - 1, $size - 1, $green);
    // Wordmark blanc à ~78% de la largeur, centré.
    $targetW = (int) round($size * 0.78);
    $targetH = (int) round($targetW * $ch / $cw);
    $maxH = (int) round($size * 0.46);
    if ($targetH > $maxH) { $targetH = $maxH; $targetW = (int) round($targetH * $cw / $ch); }
    $dx = (int) round(($size - $targetW) / 2);
    $dy = (int) round(($size - $targetH) / 2);
    imagecopyresampled($icon, $markWhite, $dx, $dy, 0, 0, $targetW, $targetH, $cw, $ch);
    return $icon;
}

function savePng(\GdImage $img, string $path): void {
    imagesavealpha($img, true);
    imagepng($img, $path);
    echo "  -> " . str_replace('\\', '/', $path) . "\n";
}

// Copie du logo complet (couleur, pour fonds clairs).
copy(__DIR__ . '/logoannat.png', $assets . '/logo-anatt.png');
echo "logo complet copié dans src/assets/logo-anatt.png\n";

// Variante blanche (pour fonds sombres) : texte noir -> blanc, barre tricolore conservée.
$white = imagecreatetruecolor($w, $h);
imagealphablending($white, false); imagesavealpha($white, true);
for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
    $c = imagecolorat($im, $x, $y);
    $a = ($c >> 24) & 0x7F;
    if ($a === 127) { imagesetpixel($white, $x, $y, ($a << 24)); continue; }
    $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
    $sat = max($r, $g, $b) - min($r, $g, $b);
    if ($sat < 40) { // gris/noir -> blanc (on garde l'alpha pour l'anti-aliasing)
        imagesetpixel($white, $x, $y, ($a << 24) | 0xFFFFFF);
    } else {          // pixel coloré (barre tricolore) -> conservé
        imagesetpixel($white, $x, $y, $c);
    }
}
imagepng($white, $assets . '/logo-anatt-white.png');
echo "variante blanche -> src/assets/logo-anatt-white.png\n";

// Icône maître 512 -> dérivés.
$icon512 = makeIcon($markWhite, $cw, $ch, 512);
savePng($icon512, $assets . '/icon-anatt.png');
savePng($icon512, $public . '/favicon-512.png');

foreach ([16, 32, 48, 180, 192] as $s) {
    $name = $s === 180 ? 'apple-touch-icon.png' : "favicon-$s.png";
    savePng(makeIcon($markWhite, $cw, $ch, $s), $public . '/' . $name);
}

// ── favicon.ico : conteneur ICO encapsulant un PNG 32x32 ──
$ico32 = makeIcon($markWhite, $cw, $ch, 32);
ob_start(); imagepng($ico32); $png = ob_get_clean();
$ico  = pack('vvv', 0, 1, 1); // réservé, type=icône, 1 image
$ico .= pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 6 + 16); // entrée du répertoire
$ico .= $png;
file_put_contents($public . '/favicon.ico', $ico);
echo "  -> " . str_replace('\\', '/', $public) . "/favicon.ico\n";

echo "OK\n";
