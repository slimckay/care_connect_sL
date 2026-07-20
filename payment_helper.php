<?php
/**
 * Payment Helper - Care Connect SL
 * Fixed platform commission: Doctors 15% · Hospitals 20%
 */

require_once __DIR__ . '/db.php';

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value)
    {
        return trim(strip_tags((string)$value));
    }
}

function platformCommissionRate(string $role): float
{
    $role = strtolower(trim($role));
    if ($role === 'hospital') {
        return 20.0;
    }
    return 15.0;
}

function platformCommissionAmount(float $amount, string $role): array
{
    $rate = platformCommissionRate($role);
    $platformFee = round($amount * ($rate / 100), 2);
    $providerEarnings = round(max(0, $amount - $platformFee), 2);
    return [
        'rate' => $rate,
        'platform_fee' => $platformFee,
        'provider_earnings' => $providerEarnings,
    ];
}

class PaymentSystem {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS wallets (
                user_id INT PRIMARY KEY,
                balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(30) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                balance_after DECIMAL(14,2) NULL,
                status VARCHAR(20) DEFAULT 'completed',
                description VARCHAR(255) NULL,
                reference VARCHAR(64) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS referral_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referral_id INT NULL,
                patient_id INT NOT NULL,
                provider_id INT NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                platform_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
                provider_earnings DECIMAL(14,2) NOT NULL DEFAULT 0,
                commission_rate DECIMAL(5,2) NULL,
                status VARCHAR(20) DEFAULT 'pending',
                payment_date DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_patient (patient_id),
                INDEX idx_provider (provider_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $this->conn->exec("CREATE TABLE IF NOT EXISTS mobile_money_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                phone_number VARCHAR(40) NULL,
                provider VARCHAR(40) NULL,
                status VARCHAR(20) DEFAULT 'pending',
                reference VARCHAR(64) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
    }

    public function ensureWallet($user_id): void
    {
        try {
            $stmt = $this->conn->prepare('SELECT user_id FROM wallets WHERE user_id = ?');
            $stmt->execute([(int)$user_id]);
            if (!$stmt->fetch()) {
                $this->conn->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)')->execute([(int)$user_id]);
            }
        } catch (Exception $e) {}
    }

    public function createWallet($user_id) {
        $this->ensureWallet($user_id);
        return true;
    }

    public function getBalance($user_id) {
        $this->ensureWallet($user_id);
        try {
            $stmt = $this->conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([(int)$user_id]);
            $result = $stmt->fetch();
            return $result ? (float)$result['balance'] : 0.00;
        } catch (PDOException $e) {
            return 0.00;
        }
    }

    private function adjustBalance(int $user_id, float $delta, string $type, string $description, ?string $reference = null): bool
    {
        $this->ensureWallet($user_id);
        $current = $this->getBalance($user_id);
        $new = round($current + $delta, 2);
        if ($new < -0.001) return false;
        $this->conn->prepare("UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?")
             ->execute([$new, $user_id]);
        $ref = $reference ?? ('TX-' . strtoupper(uniqid()));
        $this->conn->prepare("
            INSERT INTO transactions (user_id, type, amount, balance_after, status, description, reference, created_at)
            VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW())
        ")->execute([$user_id, $type, abs($delta), $new, $description, $ref]);
        return true;
    }

    public function addFunds($user_id, $amount, $description = 'Deposit', $reference = null) {
        if ($amount <= 0) return false;
        try {
            $this->conn->beginTransaction();
            $ok = $this->adjustBalance((int)$user_id, (float)$amount, 'deposit', $description, $reference);
            if (!$ok) { $this->conn->rollBack(); return false; }
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return false;
        }
    }

    public function deductFunds($user_id, $amount, $description = 'Payment', $reference = null) {
        if ($amount <= 0) return false;
        try {
            $this->conn->beginTransaction();
            $ok = $this->adjustBalance((int)$user_id, -1 * (float)$amount, 'payment', $description, $reference);
            if (!$ok) { $this->conn->rollBack(); return false; }
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return false;
        }
    }

    public function transfer($from_user, $to_user, $amount, $description = 'Consultation fee') {
        if ($amount <= 0) return ['success' => false, 'error' => 'Invalid amount'];
        if ((int)$from_user === (int)$to_user) return ['success' => false, 'error' => 'Cannot transfer to self'];
        try {
            $this->conn->beginTransaction();
            $this->ensureWallet((int)$from_user);
            $this->ensureWallet((int)$to_user);
            if ($this->getBalance((int)$from_user) < $amount) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Insufficient balance'];
            }
            $ref = 'TX-' . strtoupper(uniqid());
            $this->adjustBalance((int)$from_user, -1 * (float)$amount, 'payment', $description . ' (sent)', $ref);
            $this->adjustBalance((int)$to_user, (float)$amount, 'deposit', $description . ' (received)', $ref);
            $this->conn->commit();
            return ['success' => true, 'reference' => $ref];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['success' => false, 'error' => 'Transfer failed'];
        }
    }

    /**
     * Patient pays provider. Full amount leaves patient wallet.
     * Provider gets net after fixed commission; platform fee is recorded.
     */
    public function processReferralPayment($referral_id, $patient_id, $provider_id, $amount, $provider_role = 'doctor') {
        $amount = (float)$amount;
        if ($amount <= 0) return ['success' => false, 'error' => 'Invalid amount'];
        if ((int)$patient_id === (int)$provider_id) return ['success' => false, 'error' => 'Invalid parties'];

        try {
            $this->conn->beginTransaction();

            try {
                $rs = $this->conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $rs->execute([(int)$provider_id]);
                $row = $rs->fetch();
                if ($row && !empty($row['role'])) {
                    $provider_role = $row['role'];
                }
            } catch (Exception $e) {}

            $split = platformCommissionAmount($amount, (string)$provider_role);
            $commission_rate = $split['rate'];
            $platform_fee = $split['platform_fee'];
            $provider_earnings = $split['provider_earnings'];

            $this->ensureWallet((int)$patient_id);
            $this->ensureWallet((int)$provider_id);

            if ($this->getBalance((int)$patient_id) < $amount) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Insufficient wallet balance. Add funds first.'];
            }

            $ref = 'PAY-' . strtoupper(uniqid());

            if (!$this->adjustBalance((int)$patient_id, -$amount, 'payment', 'Consultation payment', $ref)) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Could not charge patient wallet'];
            }

            if (!$this->adjustBalance((int)$provider_id, $provider_earnings, 'deposit', 'Consultation earnings (after platform fee)', $ref)) {
                $this->conn->rollBack();
                return ['success' => false, 'error' => 'Could not credit provider'];
            }

            try {
                $this->conn->prepare("
                    INSERT INTO transactions (user_id, type, amount, balance_after, status, description, reference, created_at)
                    VALUES (?, 'commission', ?, NULL, 'completed', ?, ?, NOW())
                ")->execute([
                    (int)$provider_id,
                    $platform_fee,
                    'Platform commission ' . $commission_rate . '% retained by Care Connect',
                    $ref
                ]);
            } catch (Exception $e) {}

            try {
                $this->conn->prepare("
                    INSERT INTO referral_payments
                    (referral_id, patient_id, provider_id, amount, platform_fee, provider_earnings, commission_rate, status, payment_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
                ")->execute([
                    $referral_id ?: null,
                    (int)$patient_id,
                    (int)$provider_id,
                    $amount,
                    $platform_fee,
                    $provider_earnings,
                    $commission_rate
                ]);
            } catch (Exception $e) {
                $this->conn->prepare("
                    INSERT INTO referral_payments
                    (referral_id, patient_id, provider_id, amount, platform_fee, provider_earnings, status, payment_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
                ")->execute([
                    $referral_id ?: null,
                    (int)$patient_id,
                    (int)$provider_id,
                    $amount,
                    $platform_fee,
                    $provider_earnings
                ]);
            }

            $payment_id = (int)$this->conn->lastInsertId();
            $this->conn->commit();

            return [
                'success' => true,
                'payment_id' => $payment_id,
                'commission_rate' => $commission_rate,
                'platform_fee' => $platform_fee,
                'provider_earnings' => $provider_earnings,
                'reference' => $ref,
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Referral payment error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }

    public function getTransactionHistory($user_id, $limit = 20) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, type, amount, balance_after, status, description, reference, created_at
                FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->bindValue(1, (int)$user_id, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function initiateMobileMoney($user_id, $amount, $phone_number, $provider = 'Orange') {
        try {
            $reference = 'MM-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO mobile_money_requests
                (user_id, amount, phone_number, provider, status, reference, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([(int)$user_id, $amount, $phone_number, $provider, $reference]);
            // Demo mode: credit wallet immediately so pay flow works for demos
            $this->addFunds((int)$user_id, (float)$amount, 'Mobile money deposit (' . $provider . ')', $reference);
            try {
                $this->conn->prepare("UPDATE mobile_money_requests SET status='completed', completed_at=NOW() WHERE reference=?")
                     ->execute([$reference]);
            } catch (Exception $e) {}
            return [
                'success' => true,
                'request_id' => (int)$this->conn->lastInsertId(),
                'reference' => $reference,
                'message' => 'Funds added to your wallet. Reference: ' . $reference
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Failed to initiate payment'];
        }
    }

    public function simulateMobileMoney($request_id) {
        return true;
    }
}
