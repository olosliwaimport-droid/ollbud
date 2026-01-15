<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Elementor_Registration extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'aw_registration_form';
    }

    public function get_title(): string
    {
        return 'Formularz zapisu (Autowebinar)';
    }

    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array
    {
        return ['autowebinar'];
    }

    protected function render(): void
    {
        echo do_shortcode('[autowebinar_form]');
    }
}
