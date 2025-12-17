<?php

header('Content-Type: application/json');

$bodyRaw = file_get_contents('php://input');

if (empty($bodyRaw)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$body = json_decode($bodyRaw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON',
        'msg' => json_last_error_msg(),
        'raw' => $bodyRaw
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'received' => $body
]);
