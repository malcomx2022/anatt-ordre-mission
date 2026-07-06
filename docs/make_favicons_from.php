<?php
/*
 * Génère TOUS les favicons de l'application à partir d'une image source
 * quelconque (ex. armoiries du Bénin). Contrairement à make_favicons.php
 * (spécifique au wordmark ANaTT), ce script prend n'importe quel PNG/JPG,
 * le recadre en carré centré et produit les dérivés attendus par index.html.
 *
 * Usage :
 *   php docs/make_favicons_from.php docs/armoiries-benin.png
 *
 * Produit :
 *   frontend/public/favicon-16|32|48|192|512.png
 *   frontend/public/apple-touch-icon.png (180)
 *   frontend/public/favicon.ico (PNG 32x32 encapsulé)
 *   frontend/src/assets/icon-anatt.png (512, marque carrée in-app)
 */

$src = $argv[1] ?? null;
if (! $src || ! is_file($src)) {
    fwrite(STDERR, "Source introuvable. Usage : php docs/make_favicons_from.php <image>\n");
    exit(1);
}

$front  = dirname(__DIR__) . '/frontend';
$assets = $front . '/src/assets';
$public = $front . '/public';

$info = getimagesize($src);
$mime = $info['mime'] ?? '';
$im = match ($mime) {
    'image/png'  => imagecreatefrompng($src),
    'image/jpeg' => imagecreatefromjpeg($src),
    'image/webp' => imagecreatefromwebp($src),
    default      => null,
};
if (! $im) {
    fwrite(STDERR, "Format non supporté ({$mime}). Fournir un PNG, JPG ou WEBP.\n");
    exit(1);
}

$w = imagesx($im);
$h = imagesy($im);

// Recadrage carré centré (préserve le sujet des armoiries).
$side = min($w, $h);
$sx = (int) round(($w - $side) / 2);
$sy = (int) round(($h - $side) / 2);

/** Redimensionne la source recadrée vers un carré $size, fond transparent. */
function makeIcon(\GdImage $im, int $sx, int $sy, int $side, int $size): \GdImage
{
    $icon = imagecreatetruecolor($size, $size);
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
    imagealphablending($icon, true);
    imagecopyresampled($icon, $im, 0, 0, $sx, $sy, $size, $size, $side, $side);

    return $icon;
}

function savePng(\GdImage $img, string $path): void
{
    imagesavealpha($img, true);
    imagepng($img, $path);
    echo '  -> ' . str_replace('\\', '/', $path) . "\n";
}

// Icône maître 512 -> in-app + favicon-512.
$icon512 = makeIcon($im, $sx, $sy, $side, 512);
savePng($icon512, $assets . '/icon-anatt.png');
savePng($icon512, $public . '/favicon-512.png');

foreach ([16, 32, 48, 180, 192] as $s) {
    $name = $s === 180 ? 'apple-touch-icon.png' : "favicon-$s.png";
    savePng(makeIcon($im, $sx, $sy, $side, $s), $public . '/' . $name);
}

// favicon.ico : conteneur ICO encapsulant un PNG 32x32.
$ico32 = makeIcon($im, $sx, $sy, $side, 32);
ob_start();
imagepng($ico32);
$png = ob_get_clean();
$ico = pack('vvv', 0, 1, 1);
$ico .= pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 6 + 16);
$ico .= $png;
file_put_contents($public . '/favicon.ico', $ico);
echo '  -> ' . str_replace('\\', '/', $public) . "/favicon.ico\n";

echo "OK — favicons générés depuis " . basename($src) . "\n";
