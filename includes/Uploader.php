<?php

namespace WII;

class Uploader
{
    public static function init()
    {
        add_action('wp_ajax_wii_upload_image', [__CLASS__, 'ajaxRequest']);
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
        $image = null;
        switch ($file['type']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($download_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($download_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($download_path);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($download_path);
                break;
        }
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
            $resized = imagecreatetruecolor($scaledWidth, $scaledHeight);
            if (in_array($file['type'], ['image/png', 'image/gif']))
            {
                imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        if ($watermark) {
            $text = $original_name;
            $font_size = intval(15 * ($quality / 100));
            $angle = 0;
            $font_file = WII_PATH . 'assets/ttf/arial.ttf'; // Ensure you have a .
            $text_color = imagecolorallocatealpha($image, 255, 255, 255, 75); // White with alpha
            $padding = 10;
            $bbox = imagettfbbox($font_size, $angle, $font_file, $text);
            $text_width = abs($bbox[4] - $bbox[0]);
            $text_height = abs($bbox[5] - $bbox[1]);
            $x = imagesx($image) - $text_width - $padding;
            $y = imagesy($image) - $padding;
            imagettftext($image, $font_size, $angle, $x, $y, $text_color, $font_file, $text);
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
        $watermark = intval($_POST["watermark"] ?? 0) == 1;
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