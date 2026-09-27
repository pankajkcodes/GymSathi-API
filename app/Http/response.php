<?php
// Every response has the same shape: { "status": "success"|"error", "message": "...", "data": ... }

function sendResponse($status, $message, $data = null, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

function sendSuccess($data = null, $message = "Request successful")
{
    sendResponse("success", $message, $data);
}

function sendError($message, $data = null, $code = 400)
{
    sendResponse("error", $message, $data, $code);
}

/**
 * Paginated list payload: { items, pagination: { total, page, limit, total_pages } }
 */
function paginated(array $items, $total, $page, $limit)
{
    return [
        'items' => $items,
        'pagination' => [
            'total' => (int)$total,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total_pages' => (int)ceil($total / max(1, $limit)),
        ],
    ];
}
