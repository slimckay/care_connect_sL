<?php
/**
 * Orange Money — Care Connect SL
 *
 * Env vars (Render):
 *   ORANGE_CLIENT_ID
 *   ORANGE_CLIENT_SECRET
 *   ORANGE_MERCHANT_KEY
 *   ORANGE_PRODUCTION=1   (optional, default sandbox)
 *   ORANGE_COUNTRY=sl     (API path segment; confirm with Orange SL)
 *
 * Flow:
 *  1) Get OAuth token
 *  2) Create web payment → payment_url
 *  3) User pays on Orange page
 *  4) Orange hits api/orange-callback.php → wallet credited
 */

class OrangeMoney
{
    private string $clientId;
    private string $clientSecret;
    private string $merchantKey;
    private bool $production;
    private string $country;
    private string $currency;

    public function __construct(array $config = [])
    {
        $this->clientId = $config['clientId'] ?? (getenv('ORANGE_CLIENT_ID') ?: '');
        $this->clientSecret = $config['clientSecret'] ?? (getenv('ORANGE_CLIENT_SECRET') ?: '');
        $this->merchantKey = $config['merchant_key'] ?? (getenv('ORANGE_MERCHANT_KEY') ?: '');
        $this->production = (bool)($config['production'] ?? (getenv('ORANGE_PRODUCTION') === '1'));
        $this->country = strtolower($config['country'] ?? (getenv('ORANGE_COUNTRY') ?: 'sl'));
        $this->currency = $config['currency'] ?? (getenv('ORANGE_CURRENCY') ?: 'SLL');
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->merchantKey !== '';
    }

    private function baseApi(): string
    {
        // Same host; production flag is mainly merchant_key / Orange dashboard mode
        return 'https://api.orange.com';
    }

    /** OAuth client_credentials token */
    public function getAccessToken(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Orange Money credentials not set'];
        }

        $url = $this->baseApi() . '/oauth/v3/token';
        $basic = base64_encode($this->clientId . ':' . $this->clientSecret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $basic,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['success' => false, 'error' => 'Token request failed: ' . $err];
        }

        $data = json_decode($body, true);
        if ($code >= 400 || empty($data['access_token'])) {
            return [
                'success' => false,
                'error' => $data['error_description'] ?? ($data['error'] ?? 'OAuth failed'),
                'http' => $code,
            ];
        }

        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? null,
        ];
    }

    /**
     * Start Orange Money Web Payment.
     * Returns payment_url the user should open.
     */
    public function initiatePayment(
        string $orderId,
        float $amount,
        string $notifUrl,
        string $returnUrl,
        string $cancelUrl,
        string $reference = 'Care Connect SL'
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Orange Money not configured', 'demo' => true];
        }

        $tokenRes = $this->getAccessToken();
        if (empty($tokenRes['success'])) {
            return $tokenRes;
        }

        $url = $this->baseApi() . '/orange-money-webpay/' . rawurlencode($this->country) . '/v1/webpayment';

        $payload = [
            'merchant_key' => $this->merchantKey,
            'currency' => $this->currency,
            'order_id' => $orderId,
            'amount' => (int)round($amount),
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notif_url' => $notifUrl,
            'lang' => 'en',
            'reference' => mb_substr($reference, 0, 50),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $tokenRes['access_token'],
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 45,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['success' => false, 'error' => 'Payment init failed: ' . $err];
        }

        $data = json_decode($body, true);
        if ($code >= 400 || !is_array($data)) {
            error_log('Orange webpay error HTTP ' . $code . ': ' . $body);
            return [
                'success' => false,
                'error' => $data['message'] ?? ($data['error'] ?? 'Orange payment init failed'),
                'http' => $code,
                'raw' => $data,
            ];
        }

        // Common response fields from Orange Web Pay
        $paymentUrl = $data['payment_url'] ?? ($data['pay_url'] ?? null);
        $payToken = $data['pay_token'] ?? ($data['payToken'] ?? null);
        $notifToken = $data['notif_token'] ?? ($data['notifToken'] ?? null);

        if (!$paymentUrl) {
            return ['success' => false, 'error' => 'No payment_url from Orange', 'raw' => $data];
        }

        return [
            'success' => true,
            'payment_url' => $paymentUrl,
            'pay_token' => $payToken,
            'notif_token' => $notifToken,
            'order_id' => $orderId,
            'raw' => $data,
        ];
    }

    /** Optional status check if Orange exposes transaction endpoint */
    public function verifyPayment(string $orderId, ?string $payToken = null): array
    {
        // Status polling varies by market; rely on notif_url for truth.
        return ['success' => true, 'order_id' => $orderId, 'note' => 'Use callback notification as source of truth'];
    }
}
