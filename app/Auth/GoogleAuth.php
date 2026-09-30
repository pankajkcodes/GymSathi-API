<?php

class GoogleAuth
{
    /**
     * Verify a Google Sign-In ID token or Access Token with Google.
     *
     * @return array ['sub' => Google user id, 'email', 'name', 'picture']
     */
    public static function verify($idToken = null, $accessToken = null)
    {
        $clientIds = config('google_client_ids', []);
        if (empty($clientIds)) {
            error_log('google_client_ids is empty in config/secrets.php');
            throw new HttpException("Google Sign-In is not configured", 500);
        }

        if (empty($idToken) && empty($accessToken)) {
            throw new ValidationException("Google token is required. Please update the app.");
        }

        // 1. Try ID Token if provided (standard on mobile)
        if (!empty($idToken) && is_string($idToken)) {
            $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $claims = json_decode((string)$body, true);
            $valid = $httpCode === 200 && is_array($claims)
                && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
                && in_array($claims['aud'] ?? '', $clientIds, true)
                && in_array($claims['email_verified'] ?? '', ['true', true], true)
                && (int)($claims['exp'] ?? 0) > time()
                && !empty($claims['sub']) && !empty($claims['email']);

            if ($valid) {
                return [
                    'sub' => (string)$claims['sub'],
                    'email' => strtolower($claims['email']),
                    'name' => $claims['name'] ?? '',
                    'picture' => $claims['picture'] ?? '',
                ];
            }
        }

        // 2. Try Access Token (standard on web where Google Identity Services only returns access_token)
        if (!empty($accessToken) && is_string($accessToken)) {
            $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($accessToken));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $info = json_decode((string)$body, true);
            if ($httpCode !== 200 || !is_array($info)) {
                error_log('Google access_token tokeninfo failed: HTTP ' . $httpCode . ' body=' . $body);
            }

            // GIS access tokens: aud may differ from the client ID.
            // azp (authorized party) reliably contains the OAuth client ID.
            $candidates = array_filter([
                $info['aud'] ?? '',
                $info['azp'] ?? '',
                $info['issued_to'] ?? '',
            ]);
            $audMatch = !empty(array_intersect($candidates, $clientIds));
            $validToken = $httpCode === 200 && is_array($info)
                && $audMatch
                && (int)($info['expires_in'] ?? 0) > 0;

            if ($validToken) {
                $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $userBody = curl_exec($ch);
                $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $user = json_decode((string)$userBody, true);
                if ($userHttpCode === 200 && is_array($user) && !empty($user['sub']) && !empty($user['email'])) {
                    return [
                        'sub' => (string)$user['sub'],
                        'email' => strtolower($user['email']),
                        'name' => $user['name'] ?? '',
                        'picture' => $user['picture'] ?? '',
                    ];
                }
            }
        }

        throw new UnauthorizedException("Google sign-in failed. Please try again.");
    }
}
