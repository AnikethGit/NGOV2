<?php
/**
 * Recurring Donations API — Stub
 * TODO: Implement recurring donation logic once payment gateway recurring
 *       mandate flow is confirmed (Razorpay Subscriptions or Paytm AutoPay).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sadgurubharadwaja.org');

http_response_code(501);
echo json_encode([
    'success' => false,
    'message' => 'Recurring donations are not yet available. Please check back soon.',
    'code'    => 'FEATURE_NOT_IMPLEMENTED'
]);
