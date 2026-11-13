<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/VSU-CampusMart/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/VSU-CampusMart/config/paymongo.php';

http_response_code(200);

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (!$signatureHeader || !$rawBody || !PAYMONGO_WEBHOOK_SECRET) {
    http_response_code(400);
    exit('Missing webhook data.');
}

$parts = [];

foreach (explode(',', $signatureHeader) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) === 2) {
        $parts[$pair[0]] = $pair[1];
    }
}

$timestamp = $parts['t'] ?? '';
$testSignature = $parts['te'] ?? '';
$liveSignature = $parts['li'] ?? '';

if (!$timestamp || abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    exit('Invalid webhook timestamp.');
}

$expected = hash_hmac(
    'sha256',
    $timestamp . '.' . $rawBody,
    PAYMONGO_WEBHOOK_SECRET
);

$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    exit('Invalid JSON.');
}

$isLive = $payload['data']['attributes']['livemode'] ?? false;
$providedSignature = $isLive ? $liveSignature : $testSignature;

if (!$providedSignature || !hash_equals($expected, $providedSignature)) {
    http_response_code(401);
    exit('Invalid signature.');
}

$eventId = $payload['data']['id'] ?? '';
$eventType = $payload['data']['attributes']['type'] ?? '';
$session = $payload['data']['attributes']['data'] ?? [];
$attributes = $session['attributes'] ?? [];
$reference = $attributes['reference_number'] ?? '';

if ($eventId === '' || $reference === '') {
    http_response_code(400);
    exit('Missing event or order reference.');
}

if ($eventType !== 'checkout_session.payment.paid') {
    echo 'Event ignored.';
    exit;
}

try {
    $pdo->beginTransaction();

    $eventCheck = $pdo->prepare("SELECT id FROM payment_webhook_events WHERE event_id = ?");
    $eventCheck->execute([$eventId]);

    if ($eventCheck->fetch()) {
        $pdo->commit();
        echo 'Already processed.';
        exit;
    }

    $orderQuery = $pdo->prepare("
        SELECT id, payment_status
        FROM orders
        WHERE reference_number = ?
        FOR UPDATE
    ");
    $orderQuery->execute([$reference]);
    $order = $orderQuery->fetch();

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $paid = false;
    $payments = $attributes['payments'] ?? [];

    foreach ($payments as $payment) {
        if (($payment['attributes']['status'] ?? '') === 'paid') {
            $paid = true;
            break;
        }
    }

    if ($paid && $order['payment_status'] !== 'paid') {
        $update = $pdo->prepare("
            UPDATE orders
            SET payment_status = 'paid',
                order_status = 'processing'
            WHERE id = ?
        ");
        $update->execute([$order['id']]);
    }

    $insertEvent = $pdo->prepare("
        INSERT INTO payment_webhook_events (event_id, event_type)
        VALUES (?, ?)
    ");
    $insertEvent->execute([$eventId, $eventType]);

    $pdo->commit();

    echo 'Webhook processed.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PayMongo webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Webhook processing failed.';
}