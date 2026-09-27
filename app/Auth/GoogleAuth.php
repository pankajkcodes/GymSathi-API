<?php

class GoogleAuth
{
    /**
     * Verify a Google Sign-In ID token with Google.
     *
     * @return array ['sub' => Google user id, 'email', 'name', 'picture']
     */
    public static function verify($idToken)
    {
        $clientIds = config('google_client_ids', []);
        if (empty($clientIds)) {
            error_log('google_client_ids is empty in config/secrets.php');
            throw new HttpException("Google Sign-In is not configured", 500);
        }
        if (empty($idToken) || !is_string($idToken)) {
            throw new ValidationException("Google ID token is required. Please update the app.");
        }

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

        if (!$valid) {
            throw new UnauthorizedException("Google sign-in failed. Please try again.");
        }

        return [
            'sub' => (string)$claims['sub'],
            'email' => strtolower($claims['email']),
            'name' => $claims['name'] ?? '',
            'picture' => $claims['picture'] ?? '',
        ];
    }
}
