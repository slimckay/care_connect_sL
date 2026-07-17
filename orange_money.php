<?php
/**
 * Orange Money Integration - Care Connect SL
 * Requires the Orange Money PHP SDK: composer require youssouf/orange_money_php_sdk
 */

require_once __DIR__ . '/vendor/autoload.php';

use Donzo24\OrangeMoneySdk\OrangeMoneySdk;

class OrangeMoney {
    private $client;
    private $config;
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'clientId' => getenv('ORANGE_CLIENT_ID') ?: 'your_client_id',
            'clientSecret' => getenv('ORANGE_CLIENT_SECRET') ?: 'your_client_secret',
            'merchant_key' => getenv('ORANGE_MERCHANT_KEY') ?: 'your_merchant_key',
            'currency' => 'SLL',
            'lang' => 'en',
            'reference' => 'Care Connect SL',
            'production' => false // set to true for live
        ], $config);
        
        $this->client = new OrangeMoneySdk($this->config);
    }
    
    /**
     * Initiate a payment request
     */
    public function initiatePayment($order_id, $amount, $notif_url, $return_url, $cancel_url) {
        try {
            $response = $this->client->webPaymentTransactionInit([
                'order_id' => $order_id,
                'amount' => (string)$amount,
                'notif_url' => $notif_url,
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
            ]);
            
            if ($response) {
                return [
                    'success' => true,
                    'payment_url' => $this->client->payment_url,
                    'pay_token' => $this->client->pay_token,
                    'notif_token' => $this->client->notif_token,
                ];
            }
        } catch (Exception $e) {
            error_log("Orange Money init error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return ['success' => false, 'error' => 'Unknown error'];
    }
    
    /**
     * Verify payment status (optional)
     */
    public function verifyPayment($order_id) {
        // Implement verification using Orange API if needed
        return true;
    }
}
?>