<?php
/**
 * SMS helper — Care Connect SL
 *
 * Env (Render):
 *   SMS_PROVIDER=africastalking|log
 *   AT_USERNAME=...
 *   AT_API_KEY=...
 *   AT_FROM=CareConnect   (optional sender ID)
 *   SMS_ENABLED=1
 *
 * Without keys → demo mode: logs to sms_log, returns success so product still demos.
 */

class SmsHelper
{
    private PDO $conn;
    private string $provider;
    private bool $enabled;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->provider = strtolower(getenv('SMS_PROVIDER') ?: 'log');
        $this->enabled = getenv('SMS_ENABLED') !== '0';

        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS sms_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(40) NOT NULL,
                message TEXT NOT NULL,
                provider VARCHAR(40) NULL,
                status VARCHAR(30) DEFAULT 'queued',
                response TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
    }

    /** Normalise SL numbers toward 232… */
    public static function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[^0-9+]/', '', $phone);
        $p = ltrim($p, '+');
        if (str_starts_with($p, '00')) $p = substr($p, 2);
        // local 076… / 76… → 23276…
        if (preg_match('/^0?(2[5-9]|3[0-9]|7[0-9]|8[0-9]|9[0-9])\d{6}$/', $p)) {
            $p = '232' . ltrim($p, '0');
        }
        return $p;
    }

    public function send(string $phone, string $message): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'SMS disabled'];
        }

        $phone = self::normalizePhone($phone);
        $message = trim($message);
        if ($phone === '' || strlen($phone) < 8 || $message === '') {
            return ['ok' => false, 'error' => 'Invalid phone or message'];
        }

        // Cap SMS length roughly 2 segments
        if (strlen($message) > 300) {
            $message = substr($message, 0, 297) . '…';
        }

        $hasKeys = (getenv('AT_USERNAME') && getenv('AT_API_KEY'));

        if ($this->provider === 'africastalking' && $hasKeys) {
            $result = $this->sendAfricaTalking($phone, $message);
        } else {
            $result = [
                'ok' => true,
                'demo' => true,
                'provider' => 'log',
                'message' => 'Demo SMS logged (set AT_USERNAME + AT_API_KEY for live).',
            ];
        }

        try {
            $this->conn->prepare(
                'INSERT INTO sms_log (phone, message, provider, status, response, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([
                $phone,
                $message,
                $result['provider'] ?? $this->provider,
                !empty($result['ok']) ? (!empty($result['demo']) ? 'demo' : 'sent') : 'failed',
                mb_substr(json_encode($result), 0, 1000),
            ]);
        } catch (Exception $e) {}

        return $result;
    }

    private function sendAfricaTalking(string $phone, string $message): array
    {
        $username = getenv('AT_USERNAME');
        $apiKey = getenv('AT_API_KEY');
        $from = getenv('AT_FROM') ?: null;

        $to = '+' . ltrim($phone, '+');
        $post = [
            'username' => $username,
            'to' => $to,
            'message' => $message,
        ];
        if ($from) $post['from'] = $from;

        // Sandbox vs live
        $url = (getenv('AT_SANDBOX') === '1')
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'apiKey: ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'provider' => 'africastalking', 'error' => $err];
        }

        $data = json_decode($body, true);
        $ok = $code >= 200 && $code < 300;
        return [
            'ok' => $ok,
            'provider' => 'africastalking',
            'http' => $code,
            'raw' => $data,
            'error' => $ok ? null : ($data['message'] ?? 'SMS API error'),
        ];
    }

    /** Standard templates */
    public function referralCreated(string $phone, int $refId, string $status = 'pending'): array
    {
        $msg = "Care Connect SL: Referral #$refId received. Status: $status. "
             . "Check anytime: open care-connect-sl-1.onrender.com/status.php?id=$refId";
        return $this->send($phone, $msg);
    }

    public function referralStatus(string $phone, int $refId, string $status): array
    {
        $label = ucfirst(str_replace('_', ' ', $status));
        $msg = "Care Connect SL: Referral #$refId is now $label. "
             . "Details: care-connect-sl-1.onrender.com/status.php?id=$refId";
        return $this->send($phone, $msg);
    }
}
