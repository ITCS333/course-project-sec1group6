<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

try {
    if ($method === 'GET' && $action === 'comments') {
        sendResponse(getCommentsByResourceId($db, $resource_id));
    }

    if ($method === 'GET' && $id) {
        sendResponse(getResourceById($db, $id));
    }

    if ($method === 'GET') {
        sendResponse(getAllResources($db));
    }

    if ($method === 'POST' && $action === 'comment') {
        sendResponse(createComment($db, $data), 201);
    }

    if ($method === 'POST') {
        sendResponse(createResource($db, $data), 201);
    }

    if ($method === 'PUT') {
        sendResponse(updateResource($db, $data));
    }

    if ($method === 'DELETE' && $action === 'delete_comment') {
        sendResponse(deleteComment($db, $comment_id));
    }

    if ($method === 'DELETE') {
        sendResponse(deleteResource($db, $id));
    }

    sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

function sendResponse($data, $code = 200) {
    if (isset($data['statusCode'])) {
        $code = $data['statusCode'];
        unset($data['statusCode']);
    }

    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getAllResources($db) {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'desc');

    $allowedSort = ['title', 'created_at'];
    if (!in_array($sort, $allowedSort)) {
        $sort = 'created_at';
    }

    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'desc';
    }

    $sql = "SELECT id, title, description, link, created_at FROM resources";

    if (!empty($search)) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    $stmt->execute();

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

function getResourceById($db, $id) {
    if (empty($id) || !is_numeric($id)) {
        return [
            'success' => false,
            'message' => 'Invalid resource ID.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        return [
            'success' => true,
            'data' => $resource
        ];
    }

    return [
        'success' => false,
        'message' => 'Resource not found.',
        'statusCode' => 404
    ];
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        return [
            'success' => false,
            'message' => 'Title and link are required.',
            'statusCode' => 400
        ];
    }

    $title = trim($data['title']);
    $description = trim($data['description'] ?? '');
    $link = trim($data['link']);

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        return [
            'success' => false,
            'message' => 'Invalid URL.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $ok = $stmt->execute([$title, $description, $link]);

    if ($ok) {
        return [
            'success' => true,
            'message' => 'Resource created successfully.',
            'id' => $db->lastInsertId()
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to create resource.',
        'statusCode' => 500
    ];
}

function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        return [
            'success' => false,
            'message' => 'Valid resource ID is required.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['id']]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'success' => false,
            'message' => 'Resource not found.',
            'statusCode' => 404
        ];
    }

    $fields = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $fields[] = "title = ?";
        $values[] = trim($data['title']);
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $values[] = trim($data['description']);
    }

    if (array_key_exists('link', $data)) {
        $link = trim($data['link']);

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Invalid URL.',
                'statusCode' => 400
            ];
        }

        $fields[] = "link = ?";
        $values[] = $link;
    }

    if (empty($fields)) {
        return [
            'success' => false,
            'message' => 'No fields provided to update.',
            'statusCode' => 400
        ];
    }

    $values[] = $data['id'];

    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $ok = $stmt->execute($values);

    if ($ok) {
        return [
            'success' => true,
            'message' => 'Resource updated successfully.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to update resource.',
        'statusCode' => 500
    ];
}

function deleteResource($db, $id) {
    if (empty($id) || !is_numeric($id)) {
        return [
            'success' => false,
            'message' => 'Invalid resource ID.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'success' => false,
            'message' => 'Resource not found.',
            'statusCode' => 404
        ];
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $ok = $stmt->execute([$id]);

    if ($ok) {
        return [
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to delete resource.',
        'statusCode' => 500
    ];
}

function getCommentsByResourceId($db, $id) {
    if (empty($id) || !is_numeric($id)) {
        return [
            'success' => false,
            'message' => 'Invalid resource ID.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("
        SELECT id, resource_id, author, text, created_at
        FROM comments_resource
        WHERE resource_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->execute([$id]);

    return [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

function createComment($db, $data) {
    if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
        return [
            'success' => false,
            'message' => 'resource_id, author, and text are required.',
            'statusCode' => 400
        ];
    }

    if (!is_numeric($data['resource_id'])) {
        return [
            'success' => false,
            'message' => 'resource_id must be numeric.',
            'statusCode' => 400
        ];
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$data['resource_id']]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        return [
            'success' => false,
            'message' => 'Resource not found.',
            'statusCode' => 404
        ];
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $ok = $stmt->execute([
        $data['resource_id'],
        trim($data['author']),
        trim($data['text'])
    ]);

    if ($ok) {
        return [
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $db->lastInsertId()
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to create comment.',
        'statusCode' => 500
    ];
}

function deleteComment($db, $id) {
    if (empty($id) || !is_numeric($id)) {
        return [
            'success' => false,
            'message' => 'Invalid comment ID.',
            'statusCode' => 400
        ];
    }

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'success' => false,
            'message' => 'Comment not found.',
            'statusCode' => 404
        ];
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $ok = $stmt->execute([$id]);

    if ($ok) {
        return [
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to delete comment.',
        'statusCode' => 500
    ];
}
?>