<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_Automations
{
    public static function register_hooks(): void
    {
        add_action('aw_send_reminder', [self::class, 'handle_send_reminder'], 10, 2);
        add_action('aw_send_missed', [self::class, 'handle_send_missed'], 10, 2);
        add_action('aw_send_watch_followup', [self::class, 'handle_send_watch_followup'], 10, 2);
    }

    public static function schedule_reminders(array $settings, array $payload): void
    {
        if (($settings['reminders_enabled'] ?? 'yes') === 'no') {
            return;
        }

        $slot = (int)($payload['slot_timestamp'] ?? 0);
        if ($slot <= 0) {
            return;
        }

        $schedule = [
            'day_before' => $slot - DAY_IN_SECONDS,
            'hour_before' => $slot - HOUR_IN_SECONDS,
            'min15' => $slot - 15 * MINUTE_IN_SECONDS,
            'min5' => $slot - 5 * MINUTE_IN_SECONDS,
            'start' => $slot,
        ];

        foreach ($schedule as $key => $timestamp) {
            if ($timestamp > time()) {
                wp_schedule_single_event($timestamp, 'aw_send_reminder', [$payload, $key]);
            }
        }

        $missed_minutes = (int)($settings['missed_minutes'] ?? 15);
        if ($missed_minutes > 0) {
            $missed_at = $slot + ($missed_minutes * MINUTE_IN_SECONDS);
            wp_schedule_single_event($missed_at, 'aw_send_missed', [$payload, $missed_minutes]);
        }
    }

    public static function handle_send_reminder(array $payload, string $type): void
    {
        $settings = get_option(AW_SETTINGS_KEY, []);
        $templates = self::get_reminder_templates($settings);
        $template = $templates[$type] ?? null;
        if (!$template) {
            return;
        }

        self::send_template_email($payload, $template['subject'], $template['body'], $settings);
    }

    public static function handle_send_missed(array $payload, int $minutes): void
    {
        $token = $payload['token'] ?? '';
        if ($token === '') {
            return;
        }
        if (get_option('aw_room_view_' . $token)) {
            return;
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $subject = $settings['missed_subject'] ?? 'Nie udało się dotrzeć?';
        $body = $settings['missed_body'] ?? 'Wygląda na to, że nie udało się dołączyć. Możesz wejść ponownie tutaj: {room_url}';

        self::send_template_email($payload, $subject, $body, $settings);
    }

    public static function send_watch_followup(string $token, string $segment): void
    {
        global $wpdb;
        $registrations_table = $wpdb->prefix . AW_TABLE_REGISTRATIONS;
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$registrations_table} WHERE token = %s", $token));
        if (!$registration) {
            return;
        }

        $settings = get_option(AW_SETTINGS_KEY, []);
        $subject = $settings['followup_' . $segment . '_subject'] ?? '';
        $body = $settings['followup_' . $segment . '_body'] ?? '';
        if ($subject === '' || $body === '') {
            return;
        }

        $payload = [
            'email' => $registration->email,
            'name' => $registration->name,
            'room_url' => add_query_arg('t', $registration->token, $settings['room_page_url'] ?? home_url('/pokoj-webinarowy/')),
            'slot_timestamp' => (int)$registration->slot_timestamp,
            'token' => $registration->token,
        ];

        self::send_template_email($payload, $subject, $body, $settings);
    }

    public static function handle_send_watch_followup(array $payload, string $segment): void
    {
        self::send_watch_followup($payload['token'] ?? '', $segment);
    }

    private static function send_template_email(array $payload, string $subject, string $body, array $settings): void
    {
        $replacements = self::build_replacements($payload, $settings);
        $subject = strtr($subject, $replacements);
        $body = strtr($body, $replacements);

        wp_mail(
            $payload['email'] ?? '',
            $subject,
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    private static function build_replacements(array $payload, array $settings): array
    {
        $slot = (int)($payload['slot_timestamp'] ?? 0);
        $token = $payload['token'] ?? '';
        $room_url = $payload['room_url'] ?? '';
        $ics_url = add_query_arg([
            'action' => 'aw_download_ics',
            'token' => $token,
        ], admin_url('admin-ajax.php'));
        $google_url = add_query_arg([
            'action' => 'TEMPLATE',
            'text' => 'Webinar',
            'dates' => gmdate('Ymd\\THis\\Z', $slot) . '/' . gmdate('Ymd\\THis\\Z', $slot + (int)($settings['video_seconds'] ?? 3600)),
            'details' => 'Link do pokoju: ' . $room_url,
        ], 'https://calendar.google.com/calendar/render');

        return [
            '{name}' => $payload['name'] ?? '',
            '{email}' => $payload['email'] ?? '',
            '{room_url}' => $room_url,
            '{date}' => $slot ? wp_date('Y-m-d H:i', $slot) : '',
            '{token}' => $token,
            '{ics_url}' => $ics_url,
            '{google_calendar_url}' => $google_url,
        ];
    }

    private static function get_reminder_templates(array $settings): array
    {
        return [
            'day_before' => [
                'subject' => $settings['reminder_day_subject'] ?? 'Webinar już jutro',
                'body' => $settings['reminder_day_body'] ?? 'Przypomnienie: webinar jutro o {date}. Link: {room_url}.',
            ],
            'hour_before' => [
                'subject' => $settings['reminder_hour_subject'] ?? 'Webinar za godzinę',
                'body' => $settings['reminder_hour_body'] ?? 'Start za godzinę: {room_url}.',
            ],
            'min15' => [
                'subject' => $settings['reminder_15_subject'] ?? 'Webinar za 15 minut',
                'body' => $settings['reminder_15_body'] ?? 'Do startu zostało 15 minut: {room_url}.',
            ],
            'min5' => [
                'subject' => $settings['reminder_5_subject'] ?? 'Webinar za 5 minut',
                'body' => $settings['reminder_5_body'] ?? 'Do startu zostało 5 minut: {room_url}.',
            ],
            'start' => [
                'subject' => $settings['reminder_start_subject'] ?? 'Zaczynamy webinar',
                'body' => $settings['reminder_start_body'] ?? 'Webinar właśnie się zaczyna: {room_url}.',
            ],
        ];
    }
}
