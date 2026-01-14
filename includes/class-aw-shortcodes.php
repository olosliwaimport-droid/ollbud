<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Shortcodes
{
    private static ?AW_Shortcodes $instance = null;

    public static function get_instance(): AW_Shortcodes
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('autowebinar_form', [$this, 'render_form']);
        add_shortcode('autowebinar_room', [$this, 'render_room']);

        add_action('wp_ajax_nopriv_aw_register', [$this, 'handle_register']);
        add_action('wp_ajax_aw_register', [$this, 'handle_register']);

        add_action('wp_ajax_nopriv_aw_submit_question', [$this, 'handle_submit_question']);
        add_action('wp_ajax_aw_submit_question', [$this, 'handle_submit_question']);

        add_action('wp_ajax_nopriv_aw_fetch_questions', [$this, 'handle_fetch_questions']);
        add_action('wp_ajax_aw_fetch_questions', [$this, 'handle_fetch_questions']);
    }

    public function render_form(): string
    {
        wp_enqueue_style('aw-frontend');
        wp_enqueue_script('aw-frontend');

        ob_start();
        ?>
        <div class="aw-container">
            <form class="aw-form" id="aw-registration-form">
                <h2>Zapisz się na webinar</h2>
                <div class="aw-field">
                    <label for="aw-name">Imię</label>
                    <input type="text" id="aw-name" name="name" required />
                </div>
                <div class="aw-field">
                    <label for="aw-email">Email</label>
                    <input type="email" id="aw-email" name="email" required />
                </div>
                <div class="aw-field">
                    <label>Wybierz termin</label>
                    <div class="aw-slot-picker">
                        <select id="aw-day-select" required></select>
                        <select id="aw-time-select" required></select>
                    </div>
                </div>
                <input type="hidden" id="aw-slot-timestamp" name="slot_timestamp" />
                <div class="aw-actions">
                    <button type="submit" class="aw-button">Zarezerwuj miejsce</button>
                </div>
                <div class="aw-message" id="aw-form-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_room(): string
    {
        wp_enqueue_style('aw-frontend');
        wp_enqueue_script('aw-frontend');

        $token = sanitize_text_field($_GET['t'] ?? '');
        if ($token === '') {
            return '<div class="aw-container"><p>Brak tokenu rejestracji.</p></div>';
        }

        global $wpdb;
        $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$registrations_table} WHERE token = %s", $token));
        if (!$registration) {
            return '<div class="aw-container"><p>Nie znaleziono rejestracji.</p></div>';
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $slot_timestamp = (int)$registration->slot_timestamp;
        $video_seconds = (int)($settings['video_seconds'] ?? 3600);
        $end_action = $settings['end_action'] ?? 'cta';
        $end_redirect_url = $settings['end_redirect_url'] ?? '';
        $cta_text = $settings['cta_text'] ?? 'Dołącz do oferty';
        $cta_url = $settings['cta_url'] ?? '';
        $room_layout = $settings['room_layout'] ?? 'video_chat';
        $chat_before = $settings['chat_before'] ?? 'show';
        $chat_during = $settings['chat_during'] ?? 'show';
        $chat_after = $settings['chat_after'] ?? 'hide';

        $registration_url = $settings['registration_page_url'] ?? '';
        if ($registration_url === '') {
            $registration_url = home_url('/');
        }

        $video_markup = $this->render_video($settings);

        ob_start();
        ?>
        <div class="aw-container aw-room aw-layout-<?php echo esc_attr($room_layout); ?>" data-slot="<?php echo esc_attr((string)$slot_timestamp); ?>" data-video-seconds="<?php echo esc_attr((string)$video_seconds); ?>" data-end-action="<?php echo esc_attr($end_action); ?>" data-end-redirect="<?php echo esc_url($end_redirect_url); ?>" data-chat-before="<?php echo esc_attr($chat_before); ?>" data-chat-during="<?php echo esc_attr($chat_during); ?>" data-chat-after="<?php echo esc_attr($chat_after); ?>">
            <h2>Pokój webinarowy</h2>
            <div class="aw-room-status" id="aw-room-status"></div>
            <div class="aw-countdown" id="aw-countdown"></div>
            <div class="aw-change-slot" id="aw-change-slot">
                <a class="aw-link" href="<?php echo esc_url(add_query_arg('t', $token, $registration_url)); ?>">Zmień termin</a>
            </div>
            <div class="aw-room-content">
                <div class="aw-video" id="aw-video" style="display:none;">
                    <?php echo $video_markup; ?>
                </div>
                <div class="aw-qa" id="aw-qa-section">
                    <h3>Pytania i odpowiedzi</h3>
                    <div class="aw-qa-list" id="aw-qa-list"></div>
                    <form class="aw-qa-form" id="aw-qa-form">
                        <textarea id="aw-question" placeholder="Zadaj pytanie"></textarea>
                        <button type="submit" class="aw-button">Wyślij pytanie</button>
                    </form>
                </div>
            </div>
            <div class="aw-end-cta" id="aw-end-cta" style="display:none;">
                <?php if ($end_action === 'cta') : ?>
                    <?php if ($cta_url) : ?>
                        <a class="aw-button" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_text); ?></a>
                    <?php endif; ?>
                <?php else : ?>
                    <a class="aw-button" href="<?php echo esc_url($end_redirect_url); ?>">Przejdź dalej</a>
                <?php endif; ?>
            </div>
            <input type="hidden" id="aw-room-token" value="<?php echo esc_attr($token); ?>" />
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_register(): void
    {
        check_ajax_referer('aw_frontend_nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $slot_timestamp = (int)($_POST['slot_timestamp'] ?? 0);

        if ($name === '' || $email === '' || $slot_timestamp <= 0) {
            wp_send_json_error(['message' => 'Uzupełnij wszystkie pola.'], 422);
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $lead_time = (int)($settings['lead_time_minutes'] ?? 10) * 60;
        $now = current_time('timestamp');

        if ($slot_timestamp < ($now + $lead_time)) {
            wp_send_json_error(['message' => 'Wybrany termin jest niedostępny.'], 422);
        }

        $days_ahead = max(1, (int)($settings['registration_days_ahead'] ?? 7));
        $max_timestamp = $now + ($days_ahead * DAY_IN_SECONDS);
        if ($slot_timestamp > $max_timestamp) {
            wp_send_json_error(['message' => sprintf('Możesz wybrać termin tylko w ciągu %d dni.', $days_ahead)], 422);
        }

        global $wpdb;
        $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
        $token = wp_generate_password(32, false, false);

        $inserted = $wpdb->insert(
            $registrations_table,
            [
                'token' => $token,
                'email' => $email,
                'name' => $name,
                'slot_timestamp' => $slot_timestamp,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        if ($inserted === false) {
            wp_send_json_error(['message' => 'Nie udało się zapisać rejestracji.'], 500);
        }

        $room_url = $settings['room_page_url'] ?? '';
        if ($room_url === '') {
            $room_url = home_url('/pokoj-webinarowy/');
        }
        $room_url = add_query_arg('t', $token, $room_url);
        $mailerlite = AW_MailerLite::add_subscriber($settings, $email, $name, $room_url, $slot_timestamp);
        if (!$mailerlite['success']) {
            wp_send_json_error(['message' => 'Błąd MailerLite: ' . $mailerlite['message']], 500);
        }

        wp_send_json_success([
            'message' => 'Rejestracja zakończona sukcesem.',
            'redirect' => $room_url,
        ]);
    }

    public function handle_submit_question(): void
    {
        check_ajax_referer('aw_frontend_nonce', 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $question = sanitize_textarea_field($_POST['question'] ?? '');

        if ($token === '' || $question === '') {
            wp_send_json_error(['message' => 'Uzupełnij pytanie.'], 422);
        }

        global $wpdb;
        $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$registrations_table} WHERE token = %s", $token));
        if (!$registration) {
            wp_send_json_error(['message' => 'Brak rejestracji.'], 404);
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $faq = $this->parse_faq($settings['faq_items'] ?? [], $settings['faq_lines'] ?? '');
        $answer = '';
        $status = 'pending';
        foreach ($faq as $keyword => $faq_answer) {
            if ($keyword !== '' && str_contains(mb_strtolower($question), mb_strtolower($keyword))) {
                $answer = $faq_answer;
                $status = 'answered';
                break;
            }
        }

        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;
        $wpdb->insert(
            $questions_table,
            [
                'reg_token' => $token,
                'email' => $registration->email,
                'question' => $question,
                'answer' => $answer !== '' ? $answer : null,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'answered_at' => $answer !== '' ? current_time('mysql') : null,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        wp_send_json_success(['message' => 'Pytanie wysłane.']);
    }

    public function handle_fetch_questions(): void
    {
        check_ajax_referer('aw_frontend_nonce', 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        if ($token === '') {
            wp_send_json_error(['message' => 'Brak tokenu.'], 422);
        }

        global $wpdb;
        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$questions_table} WHERE reg_token = %s ORDER BY created_at ASC", $token));

        $data = array_map(static function ($question) {
            return [
                'id' => (int)$question->id,
                'question' => $question->question,
                'answer' => $question->answer,
                'status' => $question->status,
            ];
        }, $questions);

        wp_send_json_success(['questions' => $data]);
    }

    private function render_video(array $settings): string
    {
        $provider = $settings['video_provider'] ?? 'wistia';
        $source = $settings['video_source'] ?? '';
        $iframe = $settings['video_iframe_embed'] ?? '';

        if ($provider === 'custom' && $iframe !== '') {
            return $iframe;
        }

        if ($provider === 'wistia' && $source !== '') {
            $src = sprintf('https://fast.wistia.net/embed/iframe/%s', rawurlencode($source));
            return sprintf('<iframe src="%s" allow="autoplay; fullscreen" allowfullscreen></iframe>', esc_url($src));
        }

        if ($provider === 'vimeo' && $source !== '') {
            $src = sprintf('https://player.vimeo.com/video/%s', rawurlencode($source));
            return sprintf('<iframe src="%s" allow="autoplay; fullscreen" allowfullscreen></iframe>', esc_url($src));
        }

        if ($provider === 'self' && $source !== '') {
            return sprintf('<video controls src="%s"></video>', esc_url($source));
        }

        return '<div class="aw-video-placeholder">Wideo zostanie wyświetlone o czasie webinaru.</div>';
    }

    private function parse_faq(array $faq_items, string $lines): array
    {
        $faq = [];
        foreach ($faq_items as $item) {
            $keyword = trim((string)($item['keyword'] ?? ''));
            $answer = trim((string)($item['answer'] ?? ''));
            if ($keyword !== '' && $answer !== '') {
                $faq[$keyword] = $answer;
            }
        }

        $rows = preg_split('/\r\n|\r|\n/', $lines);
        foreach ($rows as $row) {
            $parts = array_map('trim', explode('|', (string)$row, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $faq[$parts[0]] = $parts[1];
            }
        }
        return $faq;
    }
}
