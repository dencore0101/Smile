<?php

const PHOTO_MAX_DIMENSION = 1200;
const PHOTO_THUMB_DIMENSION = 400;
const PHOTO_TARGET_BYTES = 409600;
const PHOTO_MIN_QUALITY = 70;
const PHOTO_THUMB_QUALITY = 80;
const PHOTO_MAX_PIXELS = 25000000;

function photo_thumb_relpath(string $relPath): string
{
    $dir = dirname($relPath);
    $name = pathinfo($relPath, PATHINFO_FILENAME);
    $ext = pathinfo($relPath, PATHINFO_EXTENSION);
    return ($dir === '.' ? '' : $dir . '/') . $name . '_thumb.' . $ext;
}

function photo_processing_available(): bool
{
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecreatefromstring');
}

// Returns the stored file name on success, or null when the original should be kept as-is.
function process_uploaded_photo(string $path, string $mime): ?string
{
    if (!photo_processing_available()) {
        return null;
    }

    $info = @getimagesize($path);
    if ($info === false || $info[0] * $info[1] > PHOTO_MAX_PIXELS) {
        return null;
    }

    $img = photo_load_image($path, $mime);
    if ($img === false) {
        return null;
    }

    if (in_array($mime, ['image/jpeg', 'image/jpg'], true) && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $img = photo_apply_orientation($img, (int)($exif['Orientation'] ?? 1));
        if ($img === false) {
            return null;
        }
    }

    $img = photo_resize($img, PHOTO_MAX_DIMENSION);
    $thumb = photo_resize($img, PHOTO_THUMB_DIMENSION);

    $webp = function_exists('imagewebp');
    $ext = $webp ? 'webp' : 'jpg';
    $mainData = photo_encode_within_budget($webp ? $img : photo_flatten($img), $webp);
    $thumbData = photo_encode($webp ? $thumb : photo_flatten($thumb), $webp, PHOTO_THUMB_QUALITY);

    if ($mainData === false || $thumbData === false || imagecreatefromstring($mainData) === false) {
        return null;
    }

    $dir = dirname($path);
    $base = pathinfo($path, PATHINFO_FILENAME);
    $newName = $base . '.' . $ext;
    $newPath = $dir . '/' . $newName;
    $thumbPath = $dir . '/' . $base . '_thumb.' . $ext;

    if (file_put_contents($newPath, $mainData) === false) {
        return null;
    }
    if ($newPath !== $path) {
        unlink($path);
    }
    file_put_contents($thumbPath, $thumbData);

    return $newName;
}

function photo_load_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function photo_apply_orientation($img, int $orientation)
{
    switch ($orientation) {
        case 2:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            return $img;
        case 3:
            return imagerotate($img, 180, 0);
        case 4:
            imageflip($img, IMG_FLIP_VERTICAL);
            return $img;
        case 5:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            return imagerotate($img, 90, 0);
        case 6:
            return imagerotate($img, -90, 0);
        case 7:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            return imagerotate($img, -90, 0);
        case 8:
            return imagerotate($img, 90, 0);
        default:
            return $img;
    }
}

function photo_resize($img, int $maxDimension)
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxDimension / max($w, $h));
    if ($scale >= 1) {
        return $img;
    }

    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function photo_flatten($img)
{
    $w = imagesx($img);
    $h = imagesy($img);
    $canvas = imagecreatetruecolor($w, $h);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
    return $canvas;
}

function photo_encode($img, bool $webp, int $quality): string|false
{
    ob_start();
    $ok = $webp ? imagewebp($img, null, $quality) : imagejpeg($img, null, $quality);
    $data = ob_get_clean();
    return $ok ? $data : false;
}

// Quality is lowered in steps toward PHOTO_MIN_QUALITY only while the file is over the target size.
function photo_encode_within_budget($img, bool $webp): string|false
{
    $quality = 85;
    $data = photo_encode($img, $webp, $quality);
    while ($data !== false && strlen($data) > PHOTO_TARGET_BYTES && $quality > PHOTO_MIN_QUALITY) {
        $quality -= 5;
        $data = photo_encode($img, $webp, $quality);
    }
    return $data;
}
