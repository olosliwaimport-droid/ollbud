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
        add_action('admin_post_aw_save_faq', [$this, 'handle_save_faq']);
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

        add_submenu_page(
            'autowebinar',
            'FAQ i pytania',
            'FAQ i pytania',
            'manage_options',
            'autowebinar-faq',
            [$this, 'render_faq_page']
        );
    }

    public function render_settings_page(): void
    {
        $settings = get_option(AW_SETTINGS_KEY, []);
        ?>
        <div class="wrap">
            <h1>Ustawienia webinaru</h1>
            <form id="aw-settings-form">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Shortcodes</th>
                        <td>
                            <p><strong>Strona rejestracji:</strong> <code>[autowebinar_form]</code></p>
                            <p><strong>Pokój webinaru:</strong> <code>[autowebinar_room]</code></p>
                            <p class="description">Wstaw shortcody na dowolne strony i ustaw poniżej ich adresy URL (opcjonalnie).</p>
                        </td>
                    </tr>
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
                        <th scope="row"><label for="slot_interval_minutes">Interwał slotów (min)</label></th>
                        <td><input type="number" min="1" id="slot_interval_minutes" name="slot_interval_minutes" value="<?php echo esc_attr($settings['slot_interval_minutes'] ?? 15); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="registration_days_ahead">Rejestracja na ile dni do przodu</label></th>
                        <td><input type="number" min="1" id="registration_days_ahead" name="registration_days_ahead" value="<?php echo esc_attr($settings['registration_days_ahead'] ?? 7); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="registration_page_url">URL strony rejestracji</label></th>
                        <td><input type="url" id="registration_page_url" name="registration_page_url" value="<?php echo esc_attr($settings['registration_page_url'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="room_page_url">URL pokoju webinaru</label></th>
                        <td><input type="url" id="room_page_url" name="room_page_url" value="<?php echo esc_attr($settings['room_page_url'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="video_seconds">Czas webinaru (sekundy)</label></th>
                        <td><input type="number" min="0" id="video_seconds" name="video_seconds" value="<?php echo esc_attr($settings['video_seconds'] ?? 3600); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Widoczność wideo</th>
                        <td>
                            <label for="video_before">Przed startem</label>
                            <select id="video_before" name="video_before">
                                <option value="show" <?php selected(($settings['video_before'] ?? 'hide'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['video_before'] ?? 'hide'), 'hide'); ?>>Ukryj</option>
                            </select>
                            <br />
                            <label for="video_during">W trakcie</label>
                            <select id="video_during" name="video_during">
                                <option value="show" <?php selected(($settings['video_during'] ?? 'show'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['video_during'] ?? 'show'), 'hide'); ?>>Ukryj</option>
                            </select>
                            <br />
                            <label for="video_after">Po zakończeniu</label>
                            <select id="video_after" name="video_after">
                                <option value="show" <?php selected(($settings['video_after'] ?? 'hide'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['video_after'] ?? 'hide'), 'hide'); ?>>Ukryj</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="chat_position">Położenie chatu</label></th>
                        <td>
                            <select id="chat_position" name="chat_position">
                                <option value="right" <?php selected(($settings['chat_position'] ?? 'right'), 'right'); ?>>Po prawej</option>
                                <option value="left" <?php selected(($settings['chat_position'] ?? 'right'), 'left'); ?>>Po lewej</option>
                                <option value="bottom" <?php selected(($settings['chat_position'] ?? 'right'), 'bottom'); ?>>Poniżej wideo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="room_layout">Układ pokoju</label></th>
                        <td>
                            <select id="room_layout" name="room_layout">
                                <option value="video_chat" <?php selected(($settings['room_layout'] ?? 'video_chat'), 'video_chat'); ?>>Wideo + chat</option>
                                <option value="video_only" <?php selected(($settings['room_layout'] ?? 'video_chat'), 'video_only'); ?>>Tylko wideo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Widoczność chatu</th>
                        <td>
                            <label for="chat_before">Przed startem</label>
                            <select id="chat_before" name="chat_before">
                                <option value="show" <?php selected(($settings['chat_before'] ?? 'show'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['chat_before'] ?? 'show'), 'hide'); ?>>Ukryj</option>
                            </select>
                            <br />
                            <label for="chat_during">W trakcie</label>
                            <select id="chat_during" name="chat_during">
                                <option value="show" <?php selected(($settings['chat_during'] ?? 'show'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['chat_during'] ?? 'show'), 'hide'); ?>>Ukryj</option>
                            </select>
                            <br />
                            <label for="chat_after">Po zakończeniu</label>
                            <select id="chat_after" name="chat_after">
                                <option value="show" <?php selected(($settings['chat_after'] ?? 'hide'), 'show'); ?>>Pokaż</option>
                                <option value="hide" <?php selected(($settings['chat_after'] ?? 'hide'), 'hide'); ?>>Ukryj</option>
                            </select>
                        </td>
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

        </div>
        <?php
    }

    public function render_faq_page(): void
    {
        $settings = get_option(AW_SETTINGS_KEY, []);
        $faq_items = $settings['faq_items'] ?? [];
        $updated = isset($_GET['updated']) ? (int)$_GET['updated'] : 0;

        global $wpdb;
        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$questions_table} WHERE status = %s ORDER BY created_at DESC", 'pending'));
        ?>
        <div class="wrap">
            <h1>FAQ i pytania</h1>
            <?php if ($updated === 1) : ?>
                <div class="notice notice-success is-dismissible"><p>Zapisano FAQ.</p></div>
            <?php endif; ?>
            <h2>FAQ (słowa kluczowe i odpowiedzi)</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('aw_save_faq', 'aw_faq_nonce'); ?>
                <input type="hidden" name="action" value="aw_save_faq" />
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Słowo kluczowe</th>
                            <th>Automatyczna odpowiedź</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($faq_items)) : ?>
                            <?php foreach ($faq_items as $item) : ?>
                                <tr>
                                    <td><input type="text" name="faq_keyword[]" value="<?php echo esc_attr($item['keyword'] ?? ''); ?>" class="regular-text" /></td>
                                    <td><textarea name="faq_answer[]" rows="2" style="width:100%;"><?php echo esc_textarea($item['answer'] ?? ''); ?></textarea></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr>
                            <td><input type="text" name="faq_keyword[]" value="" class="regular-text" placeholder="Nowe słowo kluczowe" /></td>
                            <td><textarea name="faq_answer[]" rows="2" style="width:100%;" placeholder="Nowa odpowiedź"></textarea></td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary">Zapisz FAQ</button>
                </p>
            </form>

            <hr />
            <h2>Pytania bez odpowiedzi</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Pytanie</th>
                        <th>Email</th>
                        <th>Odpowiedź</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($questions)) : ?>
                    <?php foreach ($questions as $question) : ?>
                        <tr>
                            <td><?php echo esc_html($question->question); ?></td>
                            <td><?php echo esc_html($question->email); ?></td>
                            <td>
                                <textarea class="aw-answer" data-question-id="<?php echo esc_attr((string)$question->id); ?>" rows="2" style="width:100%;"></textarea>
                                <button class="button aw-answer-submit" data-question-id="<?php echo esc_attr((string)$question->id); ?>">Wyślij odpowiedź</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="3">Brak pytań oczekujących.</td></tr>
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
            'slot_interval_minutes' => max(1, (int)($raw['slot_interval_minutes'] ?? 15)),
            'registration_days_ahead' => max(1, (int)($raw['registration_days_ahead'] ?? 7)),
            'registration_page_url' => esc_url_raw($raw['registration_page_url'] ?? ''),
            'room_page_url' => esc_url_raw($raw['room_page_url'] ?? ''),
            'video_seconds' => max(0, (int)($raw['video_seconds'] ?? 3600)),
            'video_before' => in_array($raw['video_before'] ?? 'hide', ['show', 'hide'], true) ? $raw['video_before'] : 'hide',
            'video_during' => in_array($raw['video_during'] ?? 'show', ['show', 'hide'], true) ? $raw['video_during'] : 'show',
            'video_after' => in_array($raw['video_after'] ?? 'hide', ['show', 'hide'], true) ? $raw['video_after'] : 'hide',
            'chat_position' => in_array($raw['chat_position'] ?? 'right', ['right', 'left', 'bottom'], true) ? $raw['chat_position'] : 'right',
            'room_layout' => in_array($raw['room_layout'] ?? 'video_chat', ['video_chat', 'video_only'], true) ? $raw['room_layout'] : 'video_chat',
            'chat_before' => in_array($raw['chat_before'] ?? 'show', ['show', 'hide'], true) ? $raw['chat_before'] : 'show',
            'chat_during' => in_array($raw['chat_during'] ?? 'show', ['show', 'hide'], true) ? $raw['chat_during'] : 'show',
            'chat_after' => in_array($raw['chat_after'] ?? 'hide', ['show', 'hide'], true) ? $raw['chat_after'] : 'hide',
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
        $updated = $wpdb->update(
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

        if ($updated !== false) {
            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$questions_table} WHERE id = %d", $question_id));
            if ($question && !empty($question->email)) {
                $settings = get_option(AW_SETTINGS_KEY, []);
                $room_url = $settings['room_page_url'] ?? '';
                if ($room_url === '') {
                    $room_url = home_url('/pokoj-webinarowy/');
                }
                $room_url = add_query_arg('t', $question->reg_token, $room_url);
                $subject = 'Odpowiedź na Twoje pytanie o webinar';
                $message = sprintf(
                    "Dziękujemy za pytanie!%s%sPytanie:%s%s%sOdpowiedź:%s%s%sWejście do pokoju:%s%s",
                    PHP_EOL,
                    PHP_EOL,
                    PHP_EOL,
                    $question->question,
                    PHP_EOL,
                    PHP_EOL,
                    $question->answer,
                    PHP_EOL,
                    PHP_EOL,
                    PHP_EOL,
                    $room_url
                );
                wp_mail($question->email, $subject, $message);
            }
        }

        wp_send_json_success(['message' => 'Odpowiedź zapisana.']);
    }

    public function handle_save_faq(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }

        check_admin_referer('aw_save_faq', 'aw_faq_nonce');

        $keywords = $_POST['faq_keyword'] ?? [];
        $answers = $_POST['faq_answer'] ?? [];
        $faq_items = [];

        foreach ($keywords as $index => $keyword) {
            $keyword_clean = sanitize_text_field($keyword);
            $answer_clean = sanitize_textarea_field($answers[$index] ?? '');
            if ($keyword_clean !== '' && $answer_clean !== '') {
                $faq_items[] = [
                    'keyword' => $keyword_clean,
                    'answer' => $answer_clean,
                ];
            }
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $settings['faq_items'] = $faq_items;
        update_option(AW_SETTINGS_KEY, $settings);

        wp_safe_redirect(admin_url('admin.php?page=autowebinar-faq&updated=1'));
        exit;
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
