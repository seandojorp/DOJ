<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\ValidationException;

/**
 * Validates and normalizes employee photos before they are stored in personal-information.Photo.
 *
 * - Checks the upload status, size, real MIME type (magic bytes, not the browser-supplied type)
 *   and that the file decodes as an image.
 * - When the GD extension is available (standard in XAMPP), the image is re-encoded as JPEG and
 *   resized. Re-encoding strips metadata (EXIF/GPS) and any non-image payload.
 * - The stored size is kept under photo_max_store_bytes so it fits XAMPP's default max_allowed_packet.
 */
final class PhotoService
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function processUpload(array $file): string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['Photo' => 'The photo is too large.']);
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException(['Photo' => 'The photo could not be uploaded. Please try again.']);
        }
        $maxUpload = (int) Config::get('upload.photo_max_upload_bytes', 5 * 1024 * 1024);
        $size = (int) filesize((string) $file['tmp_name']);
        if ($size <= 0 || $size > $maxUpload) {
            throw new ValidationException(['Photo' => 'The photo must be smaller than ' . round($maxUpload / 1048576, 1) . ' MB.']);
        }
        return self::processBytes((string) file_get_contents((string) $file['tmp_name']));
    }

    public static function processBytes(string $bytes): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new ValidationException(['Photo' => 'Only JPEG, PNG or WebP images are accepted.']);
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 50 || $info[1] < 50 || $info[0] > 8000 || $info[1] > 8000) {
            throw new ValidationException(['Photo' => 'The file is not a valid image (minimum 50 × 50 pixels).']);
        }
        $maxStore = (int) Config::get('upload.photo_max_store_bytes', 900 * 1024);

        if (!function_exists('imagecreatefromstring')) {
            if (strlen($bytes) > $maxStore) {
                throw new ValidationException(['Photo' => 'The photo must be smaller than ' . (int) ($maxStore / 1024) . ' KB.']);
            }
            return $bytes;
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new ValidationException(['Photo' => 'The image could not be read.']);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $max = (int) Config::get('upload.photo_max_dimension', 600);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency onto white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $out = '';
        foreach ([85, 75, 65, 55, 45] as $quality) {
            ob_start();
            imagejpeg($dst, null, $quality);
            $out = (string) ob_get_clean();
            if (strlen($out) <= $maxStore) {
                break;
            }
        }
        imagedestroy($dst);
        if (strlen($out) > $maxStore) {
            throw new ValidationException(['Photo' => 'The photo could not be compressed enough. Please use a smaller image.']);
        }
        return $out;
    }

    public static function mimeOf(string $bytes): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        return isset(self::ALLOWED[$mime]) ? $mime : 'application/octet-stream';
    }
}
