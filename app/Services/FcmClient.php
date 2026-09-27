<?php

class FcmClient
{
    /**
     * Helper to get a Google OAuth 2.0 Access Token from a Service Account JSON file.
     * This does not require Composer/Google Client Library.
     */
    public static function getGoogleAccessToken($serviceAccountPath)
    {
        if (!file_exists($serviceAccountPath)) {
            error_log("FCM Error: Service account file not found at $serviceAccountPath");
            return false;
        }

        $credentials = json_decode(file_get_contents($serviceAccountPath), true);
        if (!$credentials) {
            error_log("FCM Error: Invalid service account JSON");
            return false;
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
        $signature = '';

        if (!openssl_sign($signatureInput, $signature, $credentials['private_key'], 'SHA256')) {
            error_log("FCM Error: OpenSSL sign failed");
            return false;
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . "." . $base64UrlSignature;

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('FCM Auth Curl Error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? false;
    }

    /**
     * Sends FCM Notification using HTTP v1 API.
     */
    public static function sendNotification($tokens, $title, $body, $data = [])
    {
        // service-account.json lives in config/ (outside the web root)
        $serviceAccountPath = APP_ROOT . '/config/service-account.json';

        // 1. Get Access Token
        $accessToken = self::getGoogleAccessToken($serviceAccountPath);
        if (!$accessToken) {
            error_log("FCM Error: Failed to get access token");
            return false;
        }

        // 2. Read Project ID from JSON
        $credentials = json_decode(file_get_contents($serviceAccountPath), true);
        $projectId = $credentials['project_id'];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $results = [];

        // HTTP v1 sends one message per request.
        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ]
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default'
                            ]
                        ]
                    ]
                ]
            ];

            // Ensure data values are strings (required by HTTP v1)
            if (!empty($data)) {
                $stringData = [];
                foreach ($data as $key => $value) {
                    $stringData[$key] = (string)$value;
                }
                $message['message']['data'] = $stringData;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log('FCM Send Curl Error: ' . curl_error($ch));
            }
            $results[] = $result;
            curl_close($ch);
        }

        return $results;
    }
}
