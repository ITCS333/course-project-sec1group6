<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/Database.php';

function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($value)
{
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $requiredFields)
{
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return [
        'valid' => count($missing) === 0,
        'missing' => $missing
    ];
}

function getAllResources($db)
{
    $sql = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if ($search !== '') {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';

    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'desc');

    if (!in_array($order, $allowedOrder, true)) {
        $order = 'desc';
    }

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $resources
    ]);
}

function getResourceById($db, $resourceId)
{
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([(int)$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    sendResponse([
        'success' => true,
        'data' => $resource
    ]);
}

function createResource($db, $data)
{
    $validation = validateRequiredFields($data, ['title', 'link']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $link = trim($data['link']);

    if (!validateUrl($link)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid URL.'
        ], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    sendResponse([
        'success' => true,
        'message' => 'Resource created successfully.',
        'id' => $db->lastInsertId()
    ], 201);
}

function updateResource($db, $data)
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Valid resource ID is required.'
        ], 400);
    }

    $id = (int)$data['id'];

    $checkStmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }

    if (array_key_exists('link', $data)) {
        $link = trim($data['link']);

        if (!validateUrl($link)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid URL.'
            ], 400);
        }

        $fields[] = "link = ?";
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse([
            'success' => false,
            'message' => 'No fields provided to update.'
        ], 400);
    }

    $values[] = $id;

    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse([
        'success' => true,
        'message' => 'Resource updated successfully.'
    ]);
}

function deleteResource($db, $resourceId)
{
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $resourceId = (int)$resourceId;

    $checkStmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $checkStmt->execute([$resourceId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);

    sendResponse([
        'success' => true,
        'message' => 'Resource deleted successfully.'
    ]);
}

function getCommentsByResourceId($db, $resourceId)
{
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT id, resource_id, author, text, created_at
        FROM comments_resource
        WHERE resource_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([(int)$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}

function createComment($db, $data)
{
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'resource_id must be numeric.'
        ], 400);
    }

    $resourceId = (int)$data['resource_id'];
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $resourceStmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $resourceStmt->execute([$resourceId]);

    if (!$resourceStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$resourceId, $author, $text]);

    sendResponse([
        'success' => true,
        'message' => 'Comment created successfully.',
        'id' => $db->lastInsertId()
    ], 201);
}

function deleteComment($db, $commentId)
{
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID.'
        ], 400);
    }

    $commentId = (int)$commentId;

    $checkStmt = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $checkStmt->execute([$commentId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    sendResponse([
        'success' => true,
        'message' => 'Comment deleted successfully.'
    ]);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        sendResponse([
            'success' => false,
            'message' => 'Database connection failed.'
        ], 500);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!is_array($data)) {
        $data = [];
    }

    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;
    $resourceId = $_GET['resource_id'] ?? null;
    $commentId = $_GET['comment_id'] ?? null;

    if ($method === 'GET') {
        if ($action === 'comments' && $resourceId !== null) {
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment' && $commentId !== null) {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method Not Allowed.'
        ], 405);
    }
} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Internal server error.'
    ], 500);
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Internal server error.'
    ], 500);
}