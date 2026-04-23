<?php
/**
 * Course Resources API
 * 
 * RESTful API for CRUD operations on course resources and their comments.
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources($db) {
    $query = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    $sort = in_array($sort, $allowedSort) ? $sort : 'created_at';

    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'desc');
    $order = in_array($order, $allowedOrder) ? $order : 'desc';

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);

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

function getResourceById($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse([
            'success' => true,
            'data' => $resource
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }
}

function createResource($db, $data) {
    if (!is_array($data)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid JSON body.'
        ], 400);
    }

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
    $result = $stmt->execute([$title, $description, $link]);

    if ($result && $stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id' => $db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create resource.'
        ], 500);
    }
}

function updateResource($db, $data) {
    if (!is_array($data)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid JSON body.'
        ], 400);
    }

    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Resource ID is required and must be numeric.'
        ], 400);
    }

    $id = $data['id'];

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

    $query = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $values[] = $id;

    $stmt = $db->prepare($query);
    $result = $stmt->execute($values);

    if ($result) {
        sendResponse([
            'success' => true,
            'message' => 'Resource updated successfully.'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to update resource.'
        ], 500);
    }
}

function deleteResource($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $checkStmt->execute([$resourceId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $result = $stmt->execute([$resourceId]);

    if ($result && $stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete resource.'
        ], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
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
    $stmt->execute([$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}

function createComment($db, $data) {
    if (!is_array($data)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid JSON body.'
        ], 400);
    }

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

    $resourceId = $data['resource_id'];
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $checkStmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $checkStmt->execute([$resourceId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $result = $stmt->execute([$resourceId, $author, $text]);

    if ($result && $stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create comment.'
        ], 500);
    }
}

function deleteComment($db, $commentId) {
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID.'
        ], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $checkStmt->execute([$commentId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $result = $stmt->execute([$commentId]);

    if ($result && $stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment.'
        ], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByResourceId($db, $resourceId);
        } elseif (!empty($id)) {
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

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }

} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Server error occurred.'
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);

    if (!is_array($data)) {
        $data = ['success' => false, 'data' => $data];
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $requiredFields) {
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
?>