/*
<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

if (!is_array($data)) {
    $data = [];
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

function getAllResources($db)
{
    $query = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $allowedSorts = ['title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';

    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'created_at';
    }

    $order = strtolower($_GET['order'] ?? 'desc');

    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);

    if (isset($params[':search'])) {
        $stmt->bindValue(':search', $params[':search'], PDO::PARAM_STR);
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
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID'
        ], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([(int)$resourceId]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse([
            'success' => true,
            'data' => $resource
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Resource not found'
    ], 404);
}

function createResource($db, $data)
{
    $validation = validateRequiredFields($data, ['title', 'link']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $link = trim($data['link']);

    if ($title === '' || $link === '') {
        sendResponse([
            'success' => false,
            'message' => 'Title and link are required'
        ], 400);
    }

    if (!validateUrl($link)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid URL'
        ], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $success = $stmt->execute([$title, $description, $link]);

    if ($success) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully',
            'id' => $db->lastInsertId()
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed creating resource'
    ], 500);
}

function updateResource($db, $data)
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid ID'
        ], 400);
    }

    $resourceId = (int)$data['id'];

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found'
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

        if ($link === '' || !validateUrl($link)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid URL'
            ], 400);
        }

        $fields[] = "link = ?";
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse([
            'success' => false,
            'message' => 'Nothing to update'
        ], 400);
    }

    $values[] = $resourceId;

    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";

    $stmt = $db->prepare($sql);

    if ($stmt->execute($values)) {
        sendResponse([
            'success' => true,
            'message' => 'Resource updated successfully'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Update failed'
    ], 500);
}

function deleteResource($db, $resourceId)
{
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID'
        ], 400);
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([(int)$resourceId]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");

    if ($stmt->execute([(int)$resourceId])) {
        sendResponse([
            'success' => true,
            'message' => 'Resource deleted successfully'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Delete failed'
    ], 500);
}

function getCommentsByResourceId($db, $resourceId)
{
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID'
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
            'message' => 'Missing fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID'
        ], 400);
    }

    $resourceId = (int)$data['resource_id'];

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resourceId]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Resource not found'
        ], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    if ($author === '' || $text === '') {
        sendResponse([
            'success' => false,
            'message' => 'Author and text are required'
        ], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO comments_resource (resource_id, author, text)
        VALUES (?, ?, ?)
    ");

    if ($stmt->execute([$resourceId, $author, $text])) {
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully',
            'id' => $db->lastInsertId()
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Comment create failed'
    ], 500);
}

function deleteComment($db, $commentId)
{
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID'
        ], 400);
    }

    $check = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $check->execute([(int)$commentId]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");

    if ($stmt->execute([(int)$commentId])) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Delete comment failed'
    ], 500);
}

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
            'message' => 'Method not allowed'
        ], 405);
    }
} catch (PDOException $e) {
    sendResponse([
        'success' => false,
        'message' => 'Database error'
    ], 500);
} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => 'Server error'
    ], 500);
}

function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validateUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data)
{
    return htmlspecialchars(
        strip_tags(trim((string)$data)),
        ENT_QUOTES,
        'UTF-8'
    );
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

?>