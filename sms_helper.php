<?php
/**
 * SMS helper — Care Connect SL (live-ready)
 *
 * Render environment variables:
 *   SMS_ENABLED=1
 *   SMS_PROVIDER=africastalking
 *   AT_USERNAME=your_app_username     (live) or sandbox (test)
 *   AT_API_KEY=your_api_key
 *   AT_FROM=CareConnect               (optional approved sender ID)
 *   AT_SANDBOX=0                      (1 = sandbox API host only)
 *   APP_PUBLIC_URL=https://care-connect-sl-1.onrender.com
 *
 * If AT_USERNAME + AT_API_KEY are set → LIVE send (unless SMS_ENABLED=0).
 * If missing → demo log only.
 */

class SmsHelper
{
    private PDO $conn;
    private string $provider;
    private bool $enabled;
    private string $publicUrl;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->enabled = getenv('SMS_ENABLED') !== '0';
        $hasKeys = (bool)(getenv('AT_USERNAME') && getenv('AT_API_KEY'));
        $configured = strtolower(getenv('SMS_PROVIDER') ?: '');
        // Auto-use Africa's Talking when keys exist
        if ($hasKeys && ($configured === '' || $configured === 'africastalking')) {
            $this->provider = 'africastalking';
        } else {
            $this->provider = $configured !== '' ? $configured : 'log';
        }
        $this->publicUrl = rtrim(getenv('APP_PUBLIC_URL') ?: 'https://care-connect-sl-1.onrender.com', '/');

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

    public function isLive(): bool
    {
        return $this->provider === 'africastalking'
            && (bool)getenv('AT_USERNAME')
            && (bool)getenv('AT_API_KEY')
            && $this->enabled;
    }

    public function modeLabel(): string
    {
        if (!$this->enabled) return 'disabled';
        return $this->isLive() ? 'live' : 'demo';
    }

    /** Normalise SL numbers → 232… */
    public static function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[^0-9+]/', '', $phone);
        $p = ltrim((string)$p, '+');
        if (str_starts_with($p, '00')) {
            $p = substr($p, 2);
        }
        // 076123456 / 76123456 → 23276123456
        if (preg_match('/^0?([2-9]\d{7,9})$/', $p, $m)) {
            $local = $m[1];
            if (!str_starts_with($local, '232')) {
                $p = '232' . $local;
            } else {
                $p = $local;
            }
        }
        // already 232…
        if (str_starts_with($p, '232') && strlen($p) >= 11) {
            return $p;
        }
        return $p;
    }

    public function send(string $phone, string $message): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'SMS disabled (SMS_ENABLED=0)'];
        }

        $phone = self::normalizePhone($phone);
        $message = trim($message);
        if ($phone === '' || strlen($phone) < 10 || $message === '') {
            return ['ok' => false, 'error' => 'Invalid phone or empty message'];
        }
        if (strlen($message) > 300) {
            $message = substr($message, 0, 297) . '…';
        }

        if ($this->isLive()) {
            $result = $this->sendAfricaTalking($phone, $message);
        } else {
            $result = [
                'ok' => true,
                'demo' => true,
                'provider' => 'log',
                'message' => 'Demo only — add AT_USERNAME + AT_API_KEY on Render for live SMS.',
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
                mb_substr(json_encode($result), 0, 1500),
            ]);
        } catch (Exception $e) {}

        return $result;
    }

    private function sendAfricaTalking(string $phone, string $message): array
    {
        $username = trim((string)getenv('AT_USERNAME'));
        $apiKey = trim((string)getenv('AT_API_KEY'));
        $from = trim((string)(getenv('AT_FROM') ?: ''));

        $to = '+' . ltrim($phone, '+');
        $post = [
            'username' => $username,
            'to' => $to,
            'message' => $message,
        ];
        // Only send from if approved sender ID configured
        if ($from !== '' && strtolower($from) !== 'careconnect') {
            $post['from'] = $from;
        } elseif ($from !== '') {
            $post['from'] = $from;
        }

        $sandbox = getenv('AT_SANDBOX') === '1' || strtolower($username) === 'sandbox';
        $url = $sandbox
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
            return ['ok' => false, 'provider' => 'africastalking', 'error' => 'cURL: ' . $err];
        }

        $data = json_decode($body, true);
        // AT returns 201 on success typically
        $recipients = $data['SMSMessageData']['Recipients'] ?? [];
        $firstStatus = $recipients[0]['status'] ?? null;
        $firstCode = isset($recipients[0]['statusCode']) ? (int)$recipients[0]['statusCode'] : null;
        // statusCode 101 = processed / success in AT docs
        $recipientOk = $firstCode === 100 || $firstCode === 101 || strtolower((string)$firstStatus) === 'success';

        $httpOk = $code >= 200 && $code < 300;
        $ok = $httpOk && ($recipientOk || empty($recipients));

        return [
            'ok' => $ok,
            'provider' => 'africastalking',
            'sandbox' => $sandbox,
            'http' => $code,
            'to' => $to,
            'status' => $firstStatus,
            'statusCode' => $firstCode,
            'raw' => $data,
            'error' => $ok ? null : ($data['SMSMessageData']['Message'] ?? ($data['message'] ?? 'SMS send failed')),
        ];
    }

    public function referralCreated(string $phone, int $refId, string $status = 'pending'): array
    {
        $msg = "Care Connect SL: Referral #$refId received (status: $status). "
             . "Track: {$this->publicUrl}/status.php?id=$refId";
        return $this->send($phone, $msg);
    }

    public function referralStatus(string $phone, int $refId, string $status): array
    {
        $label = ucfirst(str_replace('_', ' ', $status));
        $msg = "Care Connect SL: Referral #$refId is now $label. "
             . "Details: {$this->publicUrl}/status.php?id=$refId";
        return $this->send($phone, $msg);
    }
}
