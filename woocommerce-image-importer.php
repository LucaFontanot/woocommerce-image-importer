<?php

/*
Plugin Name: Woocommerce Image Importer
Description: Quickly import images, set tags for digital downloads
Version: 1.0
Author: lucaf
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WII_PATH', plugin_dir_path(__FILE__));
define('WII_URL', plugin_dir_url(__FILE__));

require_once WII_PATH . 'vendor/autoload.php';
require_once WII_PATH . 'includes/Settings.php';
require_once WII_PATH . 'includes/Uploader.php';


add_action('plugins_loaded', function () {
    \WII\Settings::init();
    \WII\Uploader::init();
});
