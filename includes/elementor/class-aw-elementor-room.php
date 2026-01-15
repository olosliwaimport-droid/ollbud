<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Elementor_Room extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'aw_room';
    }

    public function get_title(): string
    {
        return 'Pokój webinarowy (Autowebinar)';
    }

    public function get_icon(): string
    {
        return 'eicon-play';
    }

    public function get_categories(): array
    {
        return ['autowebinar'];
    }

    protected function render(): void
    {
        echo do_shortcode('[autowebinar_room]');
    }
}
