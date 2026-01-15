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

        add_submenu_page(
            'autowebinar',
            'Uczestnicy i statystyki',
            'Uczestnicy i statystyki',
            'manage_options',
            'autowebinar-registrations',
            [$this, 'render_registrations_page']
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
                        <td>
                            <input type="number" min="1" id="lead_time_minutes" name="lead_time_minutes" value="<?php echo esc_attr($settings['lead_time_minutes'] ?? 10); ?>" />
                            <p class="description">Minimalny czas od teraz do najbliższego możliwego slotu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="slot_interval_minutes">Interwał slotów (min)</label></th>
                        <td>
                            <input type="number" min="1" id="slot_interval_minutes" name="slot_interval_minutes" value="<?php echo esc_attr($settings['slot_interval_minutes'] ?? 15); ?>" />
                            <p class="description">Co ile minut mają pojawiać się godziny do wyboru.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="registration_days_ahead">Rejestracja na ile dni do przodu</label></th>
                        <td>
                            <input type="number" min="1" id="registration_days_ahead" name="registration_days_ahead" value="<?php echo esc_attr($settings['registration_days_ahead'] ?? 7); ?>" />
                            <p class="description">Maksymalna liczba dni dostępnych do wyboru w kalendarzu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jit_enabled">Just-in-time sloty</label></th>
                        <td>
                            <select id="jit_enabled" name="jit_enabled">
                                <option value="yes" <?php selected(($settings['jit_enabled'] ?? 'yes'), 'yes'); ?>>Włączone</option>
                                <option value="no" <?php selected(($settings['jit_enabled'] ?? 'yes'), 'no'); ?>>Wyłączone</option>
                            </select>
                            <p class="description">Jeśli włączone, domyślnie pokaże tylko najbliższy termin i przycisk „Pokaż inne”.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jit_minutes">Najbliższy termin (min)</label></th>
                        <td>
                            <input type="number" min="1" id="jit_minutes" name="jit_minutes" value="<?php echo esc_attr($settings['jit_minutes'] ?? 15); ?>" />
                            <p class="description">Ile minut od teraz uznać za „najbliższy termin”.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="timezone_mode">Strefa czasowa</label></th>
                        <td>
                            <select id="timezone_mode" name="timezone_mode">
                                <option value="auto" <?php selected(($settings['timezone_mode'] ?? 'auto'), 'auto'); ?>>Auto-detekcja</option>
                                <option value="select" <?php selected(($settings['timezone_mode'] ?? 'auto'), 'select'); ?>>Ręczny wybór na formularzu</option>
                                <option value="auto_select" <?php selected(($settings['timezone_mode'] ?? 'auto'), 'auto_select'); ?>>Auto + możliwość zmiany</option>
                            </select>
                            <p class="description">Określa, czy użytkownik ma wybrać strefę czasową ręcznie.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="timezone_default">Domyślna strefa czasowa</label></th>
                        <td>
                            <input type="text" id="timezone_default" name="timezone_default" value="<?php echo esc_attr($settings['timezone_default'] ?? wp_timezone_string()); ?>" class="regular-text" />
                            <p class="description">Użyj formatu IANA, np. Europe/Warsaw.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deadline_enabled">Deadline funnel</label></th>
                        <td>
                            <select id="deadline_enabled" name="deadline_enabled">
                                <option value="yes" <?php selected(($settings['deadline_enabled'] ?? 'no'), 'yes'); ?>>Włączone</option>
                                <option value="no" <?php selected(($settings['deadline_enabled'] ?? 'no'), 'no'); ?>>Wyłączone</option>
                            </select>
                            <p class="description">Uruchamia nieprzekraczalny deadline oferty po starcie webinaru lub obejrzeniu X%.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deadline_minutes">Czas deadline (min)</label></th>
                        <td>
                            <input type="number" min="1" id="deadline_minutes" name="deadline_minutes" value="<?php echo esc_attr($settings['deadline_minutes'] ?? 30); ?>" />
                            <p class="description">Ile minut trwa oferta od momentu wyzwolenia.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deadline_trigger">Wyzwalacz deadline</label></th>
                        <td>
                            <select id="deadline_trigger" name="deadline_trigger">
                                <option value="after_start" <?php selected(($settings['deadline_trigger'] ?? 'after_start'), 'after_start'); ?>>Po starcie webinaru</option>
                                <option value="after_watch" <?php selected(($settings['deadline_trigger'] ?? 'after_start'), 'after_watch'); ?>>Po obejrzeniu X%</option>
                            </select>
                            <p class="description">Gdy ustawione „Po obejrzeniu X%”, zadziała tylko dla wideo self-hosted.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deadline_watch_percent">Próg obejrzenia (%)</label></th>
                        <td>
                            <input type="number" min="1" max="100" id="deadline_watch_percent" name="deadline_watch_percent" value="<?php echo esc_attr($settings['deadline_watch_percent'] ?? 50); ?>" />
                            <p class="description">Procent wideo wymagany do uruchomienia deadline.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reminders_enabled">Pakiet przypomnień</label></th>
                        <td>
                            <select id="reminders_enabled" name="reminders_enabled">
                                <option value="yes" <?php selected(($settings['reminders_enabled'] ?? 'yes'), 'yes'); ?>>Włączony</option>
                                <option value="no" <?php selected(($settings['reminders_enabled'] ?? 'yes'), 'no'); ?>>Wyłączony</option>
                            </select>
                            <p class="description">Automatyczne przypomnienia: 1 dzień, 1h, 15 min, 5 min, start.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="missed_minutes">Missed it (min)</label></th>
                        <td>
                            <input type="number" min="1" id="missed_minutes" name="missed_minutes" value="<?php echo esc_attr($settings['missed_minutes'] ?? 15); ?>" />
                            <p class="description">Jeśli użytkownik nie wejdzie do pokoju do N minut po starcie, wyślij email.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Szablony przypomnień (HTML)</th>
                        <td>
                            <p class="description">Dostępne zmienne: {name}, {email}, {room_url}, {date}, {token}, {ics_url}, {google_calendar_url}.</p>
                            <p><label for="reminder_day_subject">Dzień przed - temat</label></p>
                            <input type="text" id="reminder_day_subject" name="reminder_day_subject" value="<?php echo esc_attr($settings['reminder_day_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="reminder_day_body">Dzień przed - treść</label></p>
                            <textarea id="reminder_day_body" name="reminder_day_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['reminder_day_body'] ?? ''); ?></textarea>

                            <p><label for="reminder_hour_subject">1h - temat</label></p>
                            <input type="text" id="reminder_hour_subject" name="reminder_hour_subject" value="<?php echo esc_attr($settings['reminder_hour_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="reminder_hour_body">1h - treść</label></p>
                            <textarea id="reminder_hour_body" name="reminder_hour_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['reminder_hour_body'] ?? ''); ?></textarea>

                            <p><label for="reminder_15_subject">15 min - temat</label></p>
                            <input type="text" id="reminder_15_subject" name="reminder_15_subject" value="<?php echo esc_attr($settings['reminder_15_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="reminder_15_body">15 min - treść</label></p>
                            <textarea id="reminder_15_body" name="reminder_15_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['reminder_15_body'] ?? ''); ?></textarea>

                            <p><label for="reminder_5_subject">5 min - temat</label></p>
                            <input type="text" id="reminder_5_subject" name="reminder_5_subject" value="<?php echo esc_attr($settings['reminder_5_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="reminder_5_body">5 min - treść</label></p>
                            <textarea id="reminder_5_body" name="reminder_5_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['reminder_5_body'] ?? ''); ?></textarea>

                            <p><label for="reminder_start_subject">Start - temat</label></p>
                            <input type="text" id="reminder_start_subject" name="reminder_start_subject" value="<?php echo esc_attr($settings['reminder_start_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="reminder_start_body">Start - treść</label></p>
                            <textarea id="reminder_start_body" name="reminder_start_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['reminder_start_body'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Missed it - szablon (HTML)</th>
                        <td>
                            <p><label for="missed_subject">Temat</label></p>
                            <input type="text" id="missed_subject" name="missed_subject" value="<?php echo esc_attr($settings['missed_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="missed_body">Treść</label></p>
                            <textarea id="missed_body" name="missed_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['missed_body'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Follow-up po obejrzeniu (HTML)</th>
                        <td>
                            <p class="description">Wysyłka przy przekroczeniu progu oglądania wideo (self-hosted).</p>
                            <p><label for="followup_low_subject">0–10% temat</label></p>
                            <input type="text" id="followup_low_subject" name="followup_low_subject" value="<?php echo esc_attr($settings['followup_low_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="followup_low_body">0–10% treść</label></p>
                            <textarea id="followup_low_body" name="followup_low_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['followup_low_body'] ?? ''); ?></textarea>

                            <p><label for="followup_mid_subject">10–50% temat</label></p>
                            <input type="text" id="followup_mid_subject" name="followup_mid_subject" value="<?php echo esc_attr($settings['followup_mid_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="followup_mid_body">10–50% treść</label></p>
                            <textarea id="followup_mid_body" name="followup_mid_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['followup_mid_body'] ?? ''); ?></textarea>

                            <p><label for="followup_high_subject">50%+ temat</label></p>
                            <input type="text" id="followup_high_subject" name="followup_high_subject" value="<?php echo esc_attr($settings['followup_high_subject'] ?? ''); ?>" class="regular-text" />
                            <p><label for="followup_high_body">50%+ treść</label></p>
                            <textarea id="followup_high_body" name="followup_high_body" rows="3" class="large-text code"><?php echo esc_textarea($settings['followup_high_body'] ?? ''); ?></textarea>
                        </td>
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
                        <th scope="row"><label for="cta_schedule_json">Harmonogram CTA (JSON)</label></th>
                        <td>
                            <textarea id="cta_schedule_json" name="cta_schedule_json" rows="5" class="large-text code"><?php echo esc_textarea($settings['cta_schedule_json'] ?? ''); ?></textarea>
                            <p class="description">Lista CTA wg czasu od startu (sekundy). Przykład: [{"start":60,"end":300,"text":"Pobierz checklistę","url":"https://..."},{"start":720,"end":0,"text":"Umów rozmowę","url":"https://..."}].</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cta_popup_enabled">Popup CTA</label></th>
                        <td>
                            <select id="cta_popup_enabled" name="cta_popup_enabled">
                                <option value="yes" <?php selected(($settings['cta_popup_enabled'] ?? 'no'), 'yes'); ?>>Włączony</option>
                                <option value="no" <?php selected(($settings['cta_popup_enabled'] ?? 'no'), 'no'); ?>>Wyłączony</option>
                            </select>
                            <p class="description">Delikatny popup CTA pojawia się zgodnie z harmonogramem i można go zamknąć.</p>
                        </td>
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

    public function render_registrations_page(): void
    {
        global $wpdb;
        $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
        $questions_table = $wpdb->prefix . AW_TABLE_QUESTIONS;

        $registrations = $wpdb->get_results("SELECT * FROM {$registrations_table} ORDER BY created_at DESC LIMIT 200");
        $total_registrations = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$registrations_table}");
        $upcoming_registrations = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$registrations_table} WHERE slot_timestamp >= %d", current_time('timestamp')));
        $total_questions = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$questions_table}");
        $answered_questions = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$questions_table} WHERE status = %s", 'answered'));
        $pending_questions = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$questions_table} WHERE status = %s", 'pending'));
        $questions_per_registration = $total_registrations > 0 ? round($total_questions / $total_registrations, 2) : 0;
        $answer_rate = $total_questions > 0 ? round(($answered_questions / $total_questions) * 100, 1) : 0;
        ?>
        <div class="wrap">
            <h1>Uczestnicy i statystyki</h1>
            <div class="aw-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:16px 0;">
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Rejestracje</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$total_registrations); ?></div>
                </div>
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Nadchodzące</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$upcoming_registrations); ?></div>
                </div>
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Pytania</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$total_questions); ?></div>
                </div>
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Odpowiedziane</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$answered_questions); ?></div>
                    <div style="color:#64748b;"><?php echo esc_html((string)$answer_rate); ?>%</div>
                </div>
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Bez odpowiedzi</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$pending_questions); ?></div>
                </div>
                <div class="aw-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <strong>Pytania / rejestrację</strong>
                    <div style="font-size:22px;"><?php echo esc_html((string)$questions_per_registration); ?></div>
                </div>
            </div>

            <h2>Lista uczestników</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Imię</th>
                        <th>Email</th>
                        <th>Termin</th>
                        <th>Data zapisu</th>
                        <th>Pokój</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($registrations)) : ?>
                    <?php foreach ($registrations as $registration) : ?>
                        <tr>
                            <td><?php echo esc_html($registration->name); ?></td>
                            <td><?php echo esc_html($registration->email); ?></td>
                            <td><?php echo esc_html(wp_date('Y-m-d H:i', (int)$registration->slot_timestamp)); ?></td>
                            <td><?php echo esc_html($registration->created_at); ?></td>
                            <td>
                                <?php
                                $settings = get_option(AW_SETTINGS_KEY, []);
                                $room_url = $settings['room_page_url'] ?? '';
                                if ($room_url === '') {
                                    $room_url = home_url('/pokoj-webinarowy/');
                                }
                                $room_url = add_query_arg('t', $registration->token, $room_url);
                                ?>
                                <a class="button button-small" href="<?php echo esc_url($room_url); ?>" target="_blank">Otwórz</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5">Brak rejestracji.</td></tr>
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
            'jit_enabled' => ($raw['jit_enabled'] ?? 'yes') === 'no' ? 'no' : 'yes',
            'jit_minutes' => max(1, (int)($raw['jit_minutes'] ?? 15)),
            'timezone_mode' => in_array($raw['timezone_mode'] ?? 'auto', ['auto', 'select', 'auto_select'], true) ? $raw['timezone_mode'] : 'auto',
            'timezone_default' => sanitize_text_field($raw['timezone_default'] ?? wp_timezone_string()),
            'deadline_enabled' => ($raw['deadline_enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'deadline_minutes' => max(1, (int)($raw['deadline_minutes'] ?? 30)),
            'deadline_trigger' => in_array($raw['deadline_trigger'] ?? 'after_start', ['after_start', 'after_watch'], true) ? $raw['deadline_trigger'] : 'after_start',
            'deadline_watch_percent' => min(100, max(1, (int)($raw['deadline_watch_percent'] ?? 50))),
            'reminders_enabled' => ($raw['reminders_enabled'] ?? 'yes') === 'no' ? 'no' : 'yes',
            'reminder_day_subject' => sanitize_text_field($raw['reminder_day_subject'] ?? ''),
            'reminder_day_body' => wp_kses_post($raw['reminder_day_body'] ?? ''),
            'reminder_hour_subject' => sanitize_text_field($raw['reminder_hour_subject'] ?? ''),
            'reminder_hour_body' => wp_kses_post($raw['reminder_hour_body'] ?? ''),
            'reminder_15_subject' => sanitize_text_field($raw['reminder_15_subject'] ?? ''),
            'reminder_15_body' => wp_kses_post($raw['reminder_15_body'] ?? ''),
            'reminder_5_subject' => sanitize_text_field($raw['reminder_5_subject'] ?? ''),
            'reminder_5_body' => wp_kses_post($raw['reminder_5_body'] ?? ''),
            'reminder_start_subject' => sanitize_text_field($raw['reminder_start_subject'] ?? ''),
            'reminder_start_body' => wp_kses_post($raw['reminder_start_body'] ?? ''),
            'missed_minutes' => max(1, (int)($raw['missed_minutes'] ?? 15)),
            'missed_subject' => sanitize_text_field($raw['missed_subject'] ?? ''),
            'missed_body' => wp_kses_post($raw['missed_body'] ?? ''),
            'followup_low_subject' => sanitize_text_field($raw['followup_low_subject'] ?? ''),
            'followup_low_body' => wp_kses_post($raw['followup_low_body'] ?? ''),
            'followup_mid_subject' => sanitize_text_field($raw['followup_mid_subject'] ?? ''),
            'followup_mid_body' => wp_kses_post($raw['followup_mid_body'] ?? ''),
            'followup_high_subject' => sanitize_text_field($raw['followup_high_subject'] ?? ''),
            'followup_high_body' => wp_kses_post($raw['followup_high_body'] ?? ''),
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
            'cta_schedule_json' => wp_kses_post($raw['cta_schedule_json'] ?? ''),
            'cta_popup_enabled' => ($raw['cta_popup_enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
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
