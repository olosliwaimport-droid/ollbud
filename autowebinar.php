<?php
/**
 * Plugin Name: Autowebinar
 * Description: Kompletny system webinarowy z rejestracją, pokojem oraz Q&A.
 * Version: 1.0.3
 * Author: Autowebinar
 * Requires PHP: 8.3
 * Requires at least: 6.9
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AW_PLUGIN_VERSION', '1.0.3');
define('AW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AW_PLUGIN_URL', plugin_dir_url(__FILE__));

define('AW_SETTINGS_KEY', 'ollbud_aw_settings');

define('AW_TABLE_REGISTRATIONS', 'aw_registrations');

define('AW_TABLE_QUESTIONS', 'aw_questions');

require_once AW_PLUGIN_DIR . 'includes/class-aw-mailerlite.php';
require_once AW_PLUGIN_DIR . 'includes/class-aw-admin.php';
require_once AW_PLUGIN_DIR . 'includes/class-aw-shortcodes.php';

register_activation_hook(__FILE__, 'aw_activate_plugin');

function aw_activate_plugin(): void
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
    $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $registrations_sql = "CREATE TABLE {$registrations_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token VARCHAR(64) NOT NULL,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(190) NOT NULL,
        slot_timestamp BIGINT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token),
        KEY slot_timestamp (slot_timestamp)
    ) {$charset_collate};";

    $questions_sql = "CREATE TABLE {$questions_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        reg_token VARCHAR(64) NOT NULL,
        email VARCHAR(190) NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        answered_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY reg_token (reg_token),
        KEY status (status)
    ) {$charset_collate};";

    dbDelta($registrations_sql);
    dbDelta($questions_sql);

    if (!get_option(AW_SETTINGS_KEY)) {
        $defaults = [
            'mailerlite_token' => '',
            'mailerlite_group_id' => '',
            'mailerlite_api_version' => 'v3',
            'lead_time_minutes' => 10,
            'slot_interval_minutes' => 15,
            'registration_days_ahead' => 7,
            'registration_page_url' => '',
            'room_page_url' => '',
            'video_seconds' => 3600,
            'video_provider' => 'wistia',
            'video_source' => '',
            'video_iframe_embed' => '',
            'video_before' => 'hide',
            'video_during' => 'show',
            'video_after' => 'hide',
            'chat_position' => 'right',
            'room_layout' => 'video_chat',
            'chat_before' => 'show',
            'chat_during' => 'show',
            'chat_after' => 'hide',
            'end_action' => 'cta',
            'end_redirect_url' => '',
            'cta_text' => 'Dołącz do oferty',
            'cta_url' => '',
            'faq_items' => [],
            'faq_lines' => "",
        ];
        add_option(AW_SETTINGS_KEY, $defaults);
    }
}

add_action('plugins_loaded', static function () {
    AW_Admin::get_instance();
    AW_Shortcodes::get_instance();
});

add_action('wp_enqueue_scripts', static function () {
    wp_register_style('aw-frontend', AW_PLUGIN_URL . 'assets/aw-frontend.css', [], AW_PLUGIN_VERSION);
    wp_register_script('aw-frontend', AW_PLUGIN_URL . 'assets/aw-frontend.js', ['jquery'], AW_PLUGIN_VERSION, true);

    $settings = get_option(AW_SETTINGS_KEY, []);
    $localized = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aw_frontend_nonce'),
        'leadTimeMinutes' => (int)($settings['lead_time_minutes'] ?? 10),
        'slotIntervalMinutes' => (int)($settings['slot_interval_minutes'] ?? 15),
        'registrationDaysAhead' => (int)($settings['registration_days_ahead'] ?? 7),
        'videoSeconds' => (int)($settings['video_seconds'] ?? 3600),
        'serverNow' => (int)current_time('timestamp'),
        'labels' => [
            'selectDay' => 'Wybierz dzień',
            'selectTime' => 'Wybierz godzinę',
        ],
    ];

    wp_localize_script('aw-frontend', 'AWSettings', $localized);
});

add_action('admin_enqueue_scripts', static function ($hook) {
    if (str_contains((string)$hook, 'autowebinar')) {
        wp_enqueue_script('aw-admin', AW_PLUGIN_URL . 'assets/aw-admin.js', ['jquery'], AW_PLUGIN_VERSION, true);
        wp_localize_script('aw-admin', 'AWAdminSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aw_admin_nonce'),
        ]);
        wp_enqueue_style('aw-frontend', AW_PLUGIN_URL . 'assets/aw-frontend.css', [], AW_PLUGIN_VERSION);
    }
});
