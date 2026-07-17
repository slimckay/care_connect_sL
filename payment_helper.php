<?php
/**
 * Payment Helper - Care Connect SL
 * Core payment processing functions
 * Updated with commission rates: Doctors 15%, Hospitals/Clinics 20%
 */

// Include database
require_once __DIR__ . '/db.php';

class PaymentSystem {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create wallet for a new user
     */
    public function createWallet($user_id) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO wallets (user_id, balance) 
                VALUES (?, 0.00)
            ");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Create wallet error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user wallet balance
     */
    public function getBalance($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result ? (float)$result['balance'] : 0.00;
        } catch (PDOException $e) {
            error_log("Get balance error: " . $e->getMessage());
            return 0.00;
        }
    }
    
    /**
     * Add funds to user wallet
     */
    public function addFunds($user_id, $amount, $description = 'Deposit', $reference = null) {
        if ($amount <= 0) return false;
        
        try {
            $this->conn->beginTransaction();
            
            // Get current balance
            $current = $this->getBalance($user_id);
            $new_balance = $current + $amount;
            
            // Update wallet
            $stmt = $this->conn->prepare("
                UPDATE wallets SET balance = ? WHERE user_id = ?
            ");
            $stmt->execute([$new_balance, $user_id]);
            
            // Create transaction record
            $ref = $reference ?? 'TX-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO transactions 
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'deposit', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $new_balance, $description, $ref]);
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Add funds error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deduct funds from user wallet
     */
    public function deductFunds($user_id, $amount, $description = 'Payment', $reference = null) {
        if ($amount <= 0) return false;
        
        $current = $this->getBalance($user_id);
        if ($current < $amount) return false; // Insufficient funds
        
        try {
            $this->conn->beginTransaction();
            
            $new_balance = $current - $amount;
            
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_balance, $user_id]);
            
            $ref = $reference ?? 'TX-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO transactions 
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'payment', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $new_balance, $description, $ref]);
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Deduct funds error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Transfer between users (patient pays provider)
     */
    public function transfer($from_user, $to_user, $amount, $description = 'Consultation fee') {
        if ($amount <= 0) return ['success' => false, 'error' => 'Invalid amount'];
        if ($from_user == $to_user) return ['success' => false, 'error' => 'Cannot transfer to self'];
        
        $from_balance = $this->getBalance($from_user);
        if ($from_balance < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }
        
        try {
            $this->conn->beginTransaction();
            
            // Deduct from sender
            $new_from_balance = $from_balance - $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_from_balance, $from_user]);
            
            // Add to receiver
            $to_balance = $this->getBalance($to_user);
            $new_to_balance = $to_balance + $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_to_balance, $to_user]);
            
            // Create transaction records
            $ref = 'TX-' . strtoupper(uniqid());
            
            // Sender transaction
            $stmt = $this->conn->prepare("
                INSERT INTO transactions 
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'payment', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$from_user, $amount, $new_from_balance, $description . ' (sent)', $ref]);
            
            // Receiver transaction
            $stmt = $this->conn->prepare("
                INSERT INTO transactions 
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'deposit', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$to_user, $amount, $new_to_balance, $description . ' (received)', $ref]);
            
            $this->conn->commit();
            return ['success' => true, 'reference' => $ref];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Transfer error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transfer failed'];
        }
    }
    
    /**
     * Process payment for a referral
     * UPDATED: Commission rates based on provider role
     * Doctors: 15%, Hospitals/Clinics: 20%
     */
    public function processReferralPayment($referral_id, $patient_id, $provider_id, $amount, $provider_role = 'doctor') {
        try {
            $this->conn->beginTransaction();
            
            // Determine commission rate based on provider role
            if ($provider_role === 'hospital') {
                $commission_rate = 20; // 20% for hospitals/clinics
            } else {
                $commission_rate = 15; // 15% for doctors
            }
            
            $platform_fee = $amount * ($commission_rate / 100);
            $provider_earnings = $amount - $platform_fee;
            
            // Create referral payment record
            $stmt = $this->conn->prepare("
                INSERT INTO referral_payments 
                (referral_id, patient_id, provider_id, amount, platform_fee, provider_earnings, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$referral_id, $patient_id, $provider_id, $amount, $platform_fee, $provider_earnings]);
            $payment_id = $this->conn->lastInsertId();
            
            // Transfer from patient to provider (net after commission)
            // Deduct full amount from patient
            $this->deductFunds($patient_id, $amount, 'Referral payment - ' . $referral_id);
            // Add provider earnings to provider
            $this->addFunds($provider_id, $provider_earnings, 'Referral earnings - ' . $referral_id);
            
            // Platform commission (add to system account)
            // In production, you'd have a system wallet
            // For now, we track it in the payment record
            
            // Update payment status
            $stmt = $this->conn->prepare("
                UPDATE referral_payments 
                SET status = 'completed', payment_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$payment_id]);
            
            $this->conn->commit();
            return ['success' => true, 'payment_id' => $payment_id, 'commission_rate' => $commission_rate];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Referral payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Get transaction history for a user
     */
    public function getTransactionHistory($user_id, $limit = 20) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    id, type, amount, balance_after, status, 
                    description, reference, created_at
                FROM transactions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Transaction history error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Initiate mobile money payment
     */
    public function initiateMobileMoney($user_id, $amount, $phone_number, $provider = 'Orange') {
        try {
            $reference = 'MM-' . strtoupper(uniqid());
            
            $stmt = $this->conn->prepare("
                INSERT INTO mobile_money_requests 
                (user_id, amount, phone_number, provider, status, reference, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $phone_number, $provider, $reference]);
            $request_id = $this->conn->lastInsertId();
            
            // In production, call Mobile Money API here
            // For now, simulate success
            // $this->simulateMobileMoney($request_id);
            
            return [
                'success' => true, 
                'request_id' => $request_id, 
                'reference' => $reference,
                'message' => 'Mobile money request initiated. Please check your phone.'
            ];
            
        } catch (PDOException $e) {
            error_log("Mobile money error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to initiate payment'];
        }
    }
    
    /**
     * Simulate mobile money confirmation (for testing)
     */
    public function simulateMobileMoney($request_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT user_id, amount FROM mobile_money_requests WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) return false;
            
            // Add funds to user wallet
            $this->addFunds($request['user_id'], $request['amount'], 'Mobile money deposit');
            
            // Update request status
            $stmt = $this->conn->prepare("
                UPDATE mobile_money_requests 
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Simulate mobile money error: " . $e->getMessage());
            return false;
        }
    }
}
?>