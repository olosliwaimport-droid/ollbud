<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Elementor
{
    public static function register(): void
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', [self::class, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [self::class, 'register_category']);
    }

    public static function register_category($elements_manager): void
    {
        $elements_manager->add_category('autowebinar', [
            'title' => 'Autowebinar',
            'icon' => 'fa fa-video-camera',
        ]);
    }

    public static function register_widgets($widgets_manager): void
    {
        require_once AW_PLUGIN_DIR . 'includes/elementor/class-aw-elementor-registration.php';
        require_once AW_PLUGIN_DIR . 'includes/elementor/class-aw-elementor-room.php';

        $widgets_manager->register(new AW_Elementor_Registration());
        $widgets_manager->register(new AW_Elementor_Room());
    }
}
