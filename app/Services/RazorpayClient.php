<?php
// Thin Razorpay REST client.

class RazorpayClient
{
    /**
     * @param array $notes stored on the order and read back when verifying
     */
    public static function createOrder($amountRupees, $receipt, array $notes)
    {
        $order = self::request('POST', 'orders', [
            'amount' => (int)round($amountRupees * 100),
            'currency' => 'INR',
            'receipt' => substr($receipt, 0, 40),
            'payment_capture' => 1,
            'notes' => array_map('strval', $notes),
        ]);
        if (empty($order['id'])) {
            throw new HttpException("Failed to create payment order. Please try again.", 502);
        }
        return $order;
    }

    public static function fetchOrder($orderId)
    {
        if (!preg_match('/^order_[A-Za-z0-9]+$/', (string)$orderId)) {
            return null;
        }
        return self::request('GET', 'orders/' . $orderId);
    }

    public static function signatureValid($orderId, $paymentId, $signature)
    {
        if (!$orderId || !$paymentId || !$signature) {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, config('razorpay.key_secret'));
        return hash_equals($expected, (string)$signature);
    }

    private static function request($method, $path, $body = null)
    {
        $ch = curl_init('https://api.razorpay.com/v1/' . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, config('razorpay.key_id') . ':' . config('razorpay.key_secret'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode < 200 || $httpCode >= 300) {
            error_log("Razorpay $method $path failed ($httpCode): " . ($curlError ?: $result));
            return null;
        }
        return json_decode($result, true);
    }
}
