<?php

declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "خطأ: امتداد PHP GD غير مفعّل في بيئة PHP الحالية.\n");
    exit(1);
}

$sourcePath = __DIR__ . '/public/img/tayar-plus-logo.jpeg';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "خطأ: لم يتم العثور على الملف {$sourcePath}\n");
    exit(1);
}

$raw = file_get_contents($sourcePath);
$src = @imagecreatefromstring($raw);

if (!$src) {
    fwrite(STDERR, "خطأ: تعذّرت قراءة ملف الصورة.\n");
    exit(1);
}

// 1. تحديد حدود الشعار الفعلية (قص الفراغ الأبيض الزائد تلقائياً)
$w = imagesx($src);
$h = imagesy($src);
$minX = $w;
$minY = $h;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        if ($r < 245 || $g < 245 || $b < 245) {
            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

if ($maxX < $minX || $maxY < $minY) {
    $minX = 0;
    $minY = 0;
    $maxX = $w - 1;
    $maxY = $h - 1;
}

$cropW = $maxX - $minX + 1;
$cropH = $maxY - $minY + 1;

/**
 * توليد صورة مربعة PNG بخلفية بيضاء وهامش متوازن مع الحفاظ على النسبة الأصلية
 */
$renderSquarePng = static function ($source, int $sx, int $sy, int $sw, int $sh, int $size, float $paddingRatio): string {
    $canvas = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $white);

    $inner = (int) round($size * (1 - 2 * $paddingRatio));
    $scale = min($inner / $sw, $inner / $sh);

    $dstW = max(1, (int) round($sw * $scale));
    $dstH = max(1, (int) round($sh * $scale));
    $dstX = (int) round(($size - $dstW) / 2);
    $dstY = (int) round(($size - $dstH) / 2);

    imagecopyresampled($canvas, $source, $dstX, $dstY, $sx, $sy, $dstW, $dstH, $sw, $sh);

    ob_start();
    imagepng($canvas, null, 9);
    $pngBytes = (string) ob_get_clean();
    imagedestroy($canvas);

    return $pngBytes;
};

/**
 * تجميع عدة مقاسات PNG داخل ملف favicon.ico قياسي
 */
$buildIco = static function (array $images): string {
    $count = count($images);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $payload = '';
    $offset = 6 + ($count * 16);

    foreach ($images as $size => $pngData) {
        $dim = $size >= 256 ? 0 : $size;
        $len = strlen($pngData);
        $entries .= pack('CCCCvvVV', $dim, $dim, 0, 0, 1, 32, $len, $offset);
        $payload .= $pngData;
        $offset += $len;
    }

    return $header . $entries . $payload;
};

// التأكد من وجود المجلدات
foreach ([__DIR__ . '/public/icons', __DIR__ . '/public/img'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 2. حفظ المقاسات المطلوبة
file_put_contents(
    __DIR__ . '/public/icons/icon-512.png',
    $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 512, 0.12)
);

file_put_contents(
    __DIR__ . '/public/icons/icon-192.png',
    $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 192, 0.12)
);

file_put_contents(
    __DIR__ . '/public/img/tayar-plus-logo.png',
    $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 512, 0.06)
);

$icoData = $buildIco([
    16 => $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 16, 0.04),
    32 => $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 32, 0.04),
    48 => $renderSquarePng($src, $minX, $minY, $cropW, $cropH, 48, 0.04),
]);

file_put_contents(__DIR__ . '/public/favicon.ico', $icoData);

imagedestroy($src);

echo "تم إنشاء جميع مقاسات شعار تيار بلس بنجاح:\n";
echo "- public/icons/icon-512.png\n";
echo "- public/icons/icon-192.png\n";
echo "- public/img/tayar-plus-logo.png\n";
echo "- public/favicon.ico\n";