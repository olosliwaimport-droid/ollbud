<?php

if (!defined('ABSPATH')) {
    exit;
}

class AW_MailerLite
{
    public static function add_subscriber(array $settings, string $email, string $name, string $room_url, int $slot_timestamp, string $token): array
    {
        $api_token = $settings['mailerlite_token'] ?? '';
        $group_id = $settings['mailerlite_group_id'] ?? '';
        $api_version = $settings['mailerlite_api_version'] ?? 'v3';

        if ($api_token === '' || $group_id === '') {
            return [
                'success' => false,
                'message' => 'Brak konfiguracji MailerLite.',
            ];
        }

        $payload = [
            'email' => $email,
            'name' => $name,
            'fields' => [
                'webinar_room_url' => $room_url,
                'webinar_token_url' => $room_url,
                'token_url' => $room_url,
                'webinar_token' => $token,
                'webinar_datetime' => wp_date('Y-m-d H:i:s', $slot_timestamp),
                'webinar_timestamp' => (string)$slot_timestamp,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($api_version === 'v2') {
            $endpoint = sprintf('https://api.mailerlite.com/api/v2/groups/%s/subscribers', rawurlencode($group_id));
            $headers['X-MailerLite-ApiKey'] = $api_token;
        } else {
            $endpoint = 'https://connect.mailerlite.com/api/subscribers';
            $headers['Authorization'] = 'Bearer ' . $api_token;
            $payload['groups'] = [$group_id];
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'message' => 'Subscriber added.',
            ];
        }

        return [
            'success' => false,
            'message' => 'MailerLite error: ' . wp_remote_retrieve_body($response),
        ];
    }
}
