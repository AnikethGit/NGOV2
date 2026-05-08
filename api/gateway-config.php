<?php
/**
 * Gateway Config Endpoint
 * Returns the active payment gateway for the frontend.
 * Called by donate.html on load to avoid hardcoding ACTIVE_GATEWAY.
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sadgurubharadwaja.org');
header('Cache-Control: no-store');

echo json_encode([
    'gateway'    => ACTIVE_GATEWAY,
    'razorpay_key_id' => (ACTIVE_GATEWAY === 'razorpay') ? RAZORPAY_KEY_ID : null,
    'paytm_env'  => (ACTIVE_GATEWAY === 'paytm') ? PAYTM_ENV : null,
]);
