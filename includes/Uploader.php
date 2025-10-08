<?php

namespace WII;

use GdImage;

class Uploader
{
    public static function init()
    {
        add_action('wp_ajax_wii_upload_image', [__CLASS__, 'ajaxRequest']);
    }

    protected static function fileToPhpGd($file, $download_path = null) : GdImage|false
    {
        $path = $download_path;
        if ($download_path === null) {
            $tmp_dir = sys_get_temp_dir();
            $path = tempnam($tmp_dir, 'wii_upload_');
            if (!move_uploaded_file($file['tmp_name'], $path)) {
                return false;
            }
        }
        return match ($file['type']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };
    }

    protected static function resize($file, $newWidth, $newHeight)
    {
        $width = imagesx($file);
        $height = imagesy($file);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $file, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($file);
        return $resized;
    }

    protected static function handleUpload($file, $watermark, $category, $tags, $quality, $price, $label): bool
    {
        $valid_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $valid_mimes)) {
            return false;
        }
        $tmp_name = $file['tmp_name'];
        $original_name = sanitize_file_name($file['name']);
        $file_name = pathinfo($original_name, PATHINFO_FILENAME);
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $tmp_dir = sys_get_temp_dir();
        $preview_path = tempnam($tmp_dir, 'wii_preview_') . '.' . $ext;
        $secure_dir = WP_CONTENT_DIR . '/secure-downloads/';
        if (!file_exists($secure_dir)) {
            mkdir($secure_dir, 0755, true);
        }
        $htaccess = $secure_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        $random_name = uniqid($file_name . "_", true) . '.' . $ext;
        $download_path = $secure_dir . $random_name;
        if (!move_uploaded_file($tmp_name, $download_path)) {
            return false;
        }
        $image = self::fileToPhpGd($file, $download_path);
        if (!$image) {
            @unlink($download_path);
            return false;
        }

        if ($quality < 100) {
            $width = imagesx($image);
            $height = imagesy($image);
            $scaledWidth = $width;
            $scaledHeight = $height;
            if ($width > 1200 || $height > 1200){
                $factor = max($width / 1200, $height / 1200);
                $scaledWidth = $width / $factor;
                $scaledHeight = $height / $factor;
            }
            $scaledHeight = $scaledHeight * ($quality / 100);
            $scaledWidth = $scaledWidth * ($quality / 100);
            $image = self::resize($image, intval($scaledWidth), intval($scaledHeight));
        }
        if ($watermark != null) {
            $watermarkFile = self::fileToPhpGd($watermark);
            if ($watermarkFile) {
                $wmWidth = imagesx($watermarkFile);
                $wmHeight = imagesy($watermarkFile);
                $imgWidth = imagesx($image);
                $imgHeight = imagesy($image);
                $imgProportion = $imgWidth / $imgHeight;
                $wmProportion = $wmWidth / $wmHeight;
                $resizedWatermark = null;
                if ($imgProportion >= $wmProportion) {
                    $newWmWidth = $imgWidth;
                    $newWmHeight = $wmHeight * ($imgWidth / $wmWidth);
                    $resizedWatermark = self::resize($watermarkFile, intval($newWmWidth), intval($newWmHeight));
                } else {
                    $newWmHeight = $imgHeight;
                    $newWmWidth = $wmWidth * ($imgHeight / $wmHeight);
                    $resizedWatermark = self::resize($watermarkFile, intval($newWmWidth), intval($newWmHeight));
                }
                //put watermark at center
                $wmWidth = imagesx($resizedWatermark);
                $wmHeight = imagesy($resizedWatermark);
                $x = ($imgWidth - $wmWidth) / 2;
                $y = ($imgHeight - $wmHeight) / 2;
                imagealphablending($image, true);
                imagecopy($image, $resizedWatermark, intval($x), intval($y), 0, 0, $wmWidth, $wmHeight);
                imagedestroy($resizedWatermark);
            }
        }
        $saved = false;
        switch ($file['type']) {
            case 'image/jpeg':
                $saved = imagejpeg($image, $preview_path);
                break;
            case 'image/png':
                $saved = imagepng($image, $preview_path);
                break;
            case 'image/gif':
                $saved = imagegif($image, $preview_path);
                break;
            case 'image/webp':
                $saved = imagewebp($image, $preview_path);
                break;
        }
        imagedestroy($image);
        if (!$saved) {
            @unlink($download_path);
            @unlink($preview_path);
            return false;
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $preview_id = media_handle_sideload([
            'name' => $original_name,
            'type' => $file['type'],
            'tmp_name' => $preview_path,
            'error' => 0,
            'size' => filesize($preview_path)
        ], 0);
        if (is_wp_error($preview_id)) {
            @unlink($download_path);
            @unlink($preview_path);
            return false;
        }
        $product = new \WC_Product_Simple();
        $product->set_name($label);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_virtual(true);
        $product->set_downloadable(true);
        $product->set_image_id($preview_id);
        $product->set_downloads([
            uniqid('file_') => [
                'name' => $original_name,
                'file' => $download_path // percorso locale, non URL
            ]
        ]);
        $product->set_regular_price(strval($price)); // Usa il prezzo fornito
        if ($category > 0) {
            $product->set_category_ids([$category]);
        }
        if (!empty($tags)) {
            $product->set_tag_ids($tags);
        }
        $product_id = $product->save();
        @unlink($preview_path);
        return $product_id ? true : false;
    }

    public static function ajaxRequest(): void
    {
        if (!current_user_can(Settings::PERMISSION)) {
            wp_send_json_error(['message' => 'Not enough permissions.']);
            return;
        }
        if (empty($_FILES['image'])) {
            wp_send_json_error(['message' => 'File is empty uploaded.']);
            return;
        }
        if ($_FILES['image']['error'] != UPLOAD_ERR_OK) {
            if ($_FILES['image']['error'] == UPLOAD_ERR_INI_SIZE || $_FILES['image']['error'] == UPLOAD_ERR_FORM_SIZE) {
                wp_send_json_error(['message' => 'File size exceeds the allowed limit. Edit your php.ini or .htaccess file.']);
            } else {
                wp_send_json_error(['message' => 'Error during file upload.']);
            }
            return;
        }
        $file = $_FILES['image'];
        $hasWatermark = intval($_POST["watermark"] ?? 0) == 1;
        $watermark = null;
        if ($hasWatermark) {
            if (empty($_FILES['watermark_file'])) {
                wp_send_json_error(['message' => 'Watermark is empty uploaded.']);
                return;
            }
            if ($_FILES['watermark_file']['error'] != UPLOAD_ERR_OK) {
                if ($_FILES['watermark_file']['error'] == UPLOAD_ERR_INI_SIZE || $_FILES['watermark_file']['error'] == UPLOAD_ERR_FORM_SIZE) {
                    wp_send_json_error(['message' => 'Watermark size exceeds the allowed limit. Edit your php.ini or .htaccess file.']);
                } else {
                    wp_send_json_error(['message' => 'Error during watemark upload.']);
                }
                return;
            }
            $watermark = $_FILES['watermark_file'];
        }
        $category = intval($_POST["category"] ?? 0);
        $price = floatval($_POST["price"] ?? 0);
        $label = trim(sanitize_text_field($_POST["label"] ?? ""));
        $quality = max(10, min(100, intval($_POST["quality"] ?? 90)));
        $tags = explode(",", $_POST["tags"] ?? "");
        for ($i = 0; $i < count($tags); $i++) {
            $tags[$i] = intval($tags[$i]);
            if ($tags[$i] <= 0) {
                unset($tags[$i]);
            }
        }

        $result = self::handleUpload($file, $watermark, $category, $tags, $quality, $price, $label);
        if ($result) {
            wp_send_json_success(['message' => 'File uploaded successfully.']);
        } else {
            wp_send_json_error(['message' => 'Error processing the file.']);
        }
    }
}