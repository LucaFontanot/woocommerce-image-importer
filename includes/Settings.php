<?php

namespace WII;

class Settings {
    const OPT_WII = 'wii_options';
    const PERMISSION = 'manage_options';

    public static function get($key, $default = '')
    {
        $opt = get_option(self::OPT_WII, []);
        return $opt[$key] ?? $default;
    }


    public static function init()
    {
        if (!current_user_can(self::PERMISSION)) {
            return;
        }
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function menu()
    {
        if (!current_user_can(self::PERMISSION)) {
            return;
        }
        add_menu_page('Woocommerce Image Importer', 'Image Importer', self::PERMISSION, 'wii', [__CLASS__, 'render_dashboard'], 'dashicons-images-alt2');
    }

    public static function render_dashboard()
    {
        if (!current_user_can(self::PERMISSION)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        wp_enqueue_style('wii-admin-select2', WII_URL . 'assets/css/select2.css', [], '4.0.13');
        wp_enqueue_style('wii-admin', WII_URL . 'assets/css/style.css', [], '1.0.1');
        wp_enqueue_script('wii-admin-select2', WII_URL . 'assets/js/select2.js', ['jquery'], '4.0.13', true);
        include WII_PATH . 'views/dashboard.php';
    }

    public static function register()
    {
        //TODO
    }


}