<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Admin
{
    private static ?AW_Admin $instance = null;

    public static function get_instance(): AW_Admin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_aw_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_aw_admin_answer', [$this, 'handle_admin_answer']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'Autowebinar',
            'Autowebinar',
            'manage_options',
            'autowebinar',
            [$this, 'render_settings_page'],
            'dashicons-video-alt3'
        );
    }

    public function render_settings_page(): void
    {
        $settings = get_option(AW_SETTINGS_KEY, []);
        global $wpdb;
        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;
        $questions = $wpdb->get_results("SELECT * FROM {$questions_table} ORDER BY created_at DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>Ustawienia webinaru</h1>
            <form id="aw-settings-form">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mailerlite_token">MailerLite token</label></th>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="password" id="mailerlite_token" name="mailerlite_token" value="<?php echo esc_attr($settings['mailerlite_token'] ?? ''); ?>" class="regular-text" />
                                <button type="button" class="button" id="aw-toggle-token">Pokaż</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mailerlite_group_id">MailerLite Group ID</label></th>
                        <td><input type="text" id="mailerlite_group_id" name="mailerlite_group_id" value="<?php echo esc_attr($settings['mailerlite_group_id'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mailerlite_api_version">MailerLite API</label></th>
                        <td>
                            <select id="mailerlite_api_version" name="mailerlite_api_version">
                                <option value="v3" <?php selected(($settings['mailerlite_api_version'] ?? 'v3'), 'v3'); ?>>API v3</option>
                                <option value="v2" <?php selected(($settings['mailerlite_api_version'] ?? 'v3'), 'v2'); ?>>API v2</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lead_time_minutes">Lead time (min)</label></th>
                        <td><input type="number" min="1" id="lead_time_minutes" name="lead_time_minutes" value="<?php echo esc_attr($settings['lead_time_minutes'] ?? 10); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="video_seconds">Czas webinaru (sekundy)</label></th>
                        <td><input type="number" min="60" id="video_seconds" name="video_seconds" value="<?php echo esc_attr($settings['video_seconds'] ?? 3600); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="video_provider">Provider wideo</label></th>
                        <td>
                            <select id="video_provider" name="video_provider">
                                <option value="wistia" <?php selected(($settings['video_provider'] ?? 'wistia'), 'wistia'); ?>>Wistia</option>
                                <option value="vimeo" <?php selected(($settings['video_provider'] ?? 'wistia'), 'vimeo'); ?>>Vimeo</option>
                                <option value="self" <?php selected(($settings['video_provider'] ?? 'wistia'), 'self'); ?>>Self-hosted</option>
                                <option value="custom" <?php selected(($settings['video_provider'] ?? 'wistia'), 'custom'); ?>>Custom iframe</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="video_source">ID/URL wideo</label></th>
                        <td><input type="text" id="video_source" name="video_source" value="<?php echo esc_attr($settings['video_source'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="video_iframe_embed">Iframe embed</label></th>
                        <td>
                            <textarea id="video_iframe_embed" name="video_iframe_embed" rows="4" class="large-text code"><?php echo esc_textarea($settings['video_iframe_embed'] ?? ''); ?></textarea>
                            <p class="description">Pole tylko dla custom iframe.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="end_action">Akcja po webinarze</label></th>
                        <td>
                            <select id="end_action" name="end_action">
                                <option value="cta" <?php selected(($settings['end_action'] ?? 'cta'), 'cta'); ?>>Pokaż CTA</option>
                                <option value="redirect" <?php selected(($settings['end_action'] ?? 'cta'), 'redirect'); ?>>Przekieruj</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="end_redirect_url">URL przekierowania</label></th>
                        <td><input type="url" id="end_redirect_url" name="end_redirect_url" value="<?php echo esc_attr($settings['end_redirect_url'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cta_text">Tekst CTA</label></th>
                        <td><input type="text" id="cta_text" name="cta_text" value="<?php echo esc_attr($settings['cta_text'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cta_url">URL CTA</label></th>
                        <td><input type="url" id="cta_url" name="cta_url" value="<?php echo esc_attr($settings['cta_url'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="faq_lines">FAQ (słowo|odpowiedź)</label></th>
                        <td>
                            <textarea id="faq_lines" name="faq_lines" rows="6" class="large-text code"><?php echo esc_textarea($settings['faq_lines'] ?? ''); ?></textarea>
                            <p class="description">Każda linia: słowo kluczowe | odpowiedź.</p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary">Zapisz ustawienia</button>
                </p>
            </form>

            <hr />
            <h2>Q&A</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Pytanie</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Odpowiedź</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($questions)) : ?>
                    <?php foreach ($questions as $question) : ?>
                        <tr>
                            <td><?php echo esc_html($question->question); ?></td>
                            <td><?php echo esc_html($question->email); ?></td>
                            <td><?php echo esc_html($question->status); ?></td>
                            <td>
                                <textarea class="aw-answer" data-question-id="<?php echo esc_attr((string)$question->id); ?>" rows="2" style="width:100%;"><?php echo esc_textarea($question->answer ?? ''); ?></textarea>
                                <button class="button aw-answer-submit" data-question-id="<?php echo esc_attr((string)$question->id); ?>">Zapisz odpowiedź</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="4">Brak pytań.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_save_settings(): void
    {
        check_ajax_referer('aw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Brak uprawnień.'], 403);
        }

        $raw = wp_unslash($_POST);

        $decoded_token = $this->maybe_decode_base64($raw['mailerlite_token'] ?? '');
        $decoded_iframe = $this->maybe_decode_base64($raw['video_iframe_embed'] ?? '');

        $allowed_iframe = [
            'iframe' => [
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allow' => true,
                'allowfullscreen' => true,
                'title' => true,
            ],
        ];

        $settings = [
            'mailerlite_token' => sanitize_text_field($decoded_token),
            'mailerlite_group_id' => sanitize_text_field($raw['mailerlite_group_id'] ?? ''),
            'mailerlite_api_version' => in_array($raw['mailerlite_api_version'] ?? 'v3', ['v2', 'v3'], true) ? $raw['mailerlite_api_version'] : 'v3',
            'lead_time_minutes' => max(1, (int)($raw['lead_time_minutes'] ?? 10)),
            'video_seconds' => max(60, (int)($raw['video_seconds'] ?? 3600)),
            'video_provider' => in_array($raw['video_provider'] ?? 'wistia', ['wistia', 'vimeo', 'self', 'custom'], true) ? $raw['video_provider'] : 'wistia',
            'video_source' => sanitize_text_field($raw['video_source'] ?? ''),
            'video_iframe_embed' => wp_kses($decoded_iframe, $allowed_iframe),
            'end_action' => in_array($raw['end_action'] ?? 'cta', ['cta', 'redirect'], true) ? $raw['end_action'] : 'cta',
            'end_redirect_url' => esc_url_raw($raw['end_redirect_url'] ?? ''),
            'cta_text' => sanitize_text_field($raw['cta_text'] ?? ''),
            'cta_url' => esc_url_raw($raw['cta_url'] ?? ''),
            'faq_lines' => sanitize_textarea_field($raw['faq_lines'] ?? ''),
        ];

        update_option(AW_SETTINGS_KEY, $settings);

        wp_send_json_success(['message' => 'Ustawienia zapisane.']);
    }

    public function handle_admin_answer(): void
    {
        check_ajax_referer('aw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Brak uprawnień.'], 403);
        }

        $question_id = (int)($_POST['question_id'] ?? 0);
        $answer = sanitize_textarea_field($_POST['answer'] ?? '');

        if ($question_id <= 0 || $answer === '') {
            wp_send_json_error(['message' => 'Brak danych.'], 422);
        }

        global $wpdb;
        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;
        $wpdb->update(
            $questions_table,
            [
                'answer' => $answer,
                'status' => 'answered',
                'answered_at' => current_time('mysql'),
            ],
            ['id' => $question_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        wp_send_json_success(['message' => 'Odpowiedź zapisana.']);
    }

    private function maybe_decode_base64(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded === false) {
            return $trimmed;
        }

        return $decoded;
    }
}
