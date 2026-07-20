<?php
/**
 * Stripe configuration — Care Connect SL
 * Set these as environment variables on Render (recommended):
 *   STRIPE_SECRET_KEY
 *   STRIPE_PUBLISHABLE_KEY
 *   STRIPE_WEBHOOK_SECRET
 *   STRIPE_CURRENCY (default: usd — change when SLL supported for your account)
 */

if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
}
if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: '');
}
if (!defined('STRIPE_WEBHOOK_SECRET')) {
    define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');
}
if (!defined('STRIPE_CURRENCY')) {
    // Stripe may not list SLL on all accounts — use usd for card tests; map amounts in metadata
    define('STRIPE_CURRENCY', strtolower(getenv('STRIPE_CURRENCY') ?: 'usd'));
}

function stripeConfigured(): bool
{
    return STRIPE_SECRET_KEY !== '' && STRIPE_WEBHOOK_SECRET !== '';
}
