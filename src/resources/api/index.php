<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once _DIR_ . '/db.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;



try {

    if ($method === 'GET' && $action === 'comments') {
        echo json_encode(getCommentsByResourceId($db, $resource_id));
        exit;
    }

    if ($method === 'GET' && $id) {
        echo json_encode(getResourceById($db, $id));
        exit;
    }

    if ($method === 'GET') {
        echo json_encode(getAllResources($db));
        exit;
    }

    if ($method === 'POST' && $action === 'comment') {
        echo json_encode(createComment($db, $data));
        exit;
    }

    if ($method === 'POST') {
        echo json_encode(createResource($db, $data));
        exit;
    }

    if ($method === 'PUT') {
        echo json_encode(updateResource($db, $data));
        exit;
    }

    if ($method === 'DELETE' && $action === 'delete_comment') {
        echo json_encode(deleteComment($db, $comment_id));
        exit;
    }

    if ($method === 'DELETE') {
        echo json_encode(deleteResource($db, $id));
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}



function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getAllResources($db) {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'desc');

    $allowedSort = ['title', 'created_at'];
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';

    if (!in_array($order, ['asc', 'desc'])) $order = 'desc';

    $sql = "SELECT * FROM resources";

    if ($search) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }

    $stmt->execute();

    return ['success' => true, 'data' => $stmt->fetchAll()];
}

function getResourceById($db, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();

    if ($res) {
        return ['success' => true, 'data' => $res];
    }

    http_response_code(404);
    return ['success' => false];
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        http_response_code(400);
        return ['success' => false];
    }

    if (!filter_var($data['link'], FILTER_VALIDATE_URL)) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $ok = $stmt->execute([
        $data['title'],
        $data['description'] ?? '',
        $data['link']
    ]);

    if ($ok) {
        http_response_code(201);
        return [
            'success' => true,
            'id' => $db->lastInsertId()
        ];
    }

    http_response_code(500);
    return ['success' => false];
}

function updateResource($db, $data) {
    if (empty($data['id'])) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$data['id']]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        return ['success' => false];
    }

    $fields = [];
    $values = [];

    if (!empty($data['title'])) {
        $fields[] = "title=?";
        $values[] = $data['title'];
    }

    if (!empty($data['description'])) {
        $fields[] = "description=?";
        $values[] = $data['description'];
    }

    if (!empty($data['link'])) {
        if (!filter_var($data['link'], FILTER_VALIDATE_URL)) {
            http_response_code(400);
            return ['success' => false];
        }
        $fields[] = "link=?";
        $values[] = $data['link'];
    }

    if (!$fields) {
        http_response_code(400);
        return ['success' => false];
    }

    $values[] = $data['id'];

    $sql = "UPDATE resources SET " . implode(',', $fields) . " WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    return ['success' => true];
}

function deleteResource($db, $id) {
    if (!$id) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        return ['success' => false];
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    return ['success' => true];
}

function getCommentsByResourceId($db, $id) {
    if (!$id) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id=?");
    $stmt->execute([$id]);

    return ['success' => true, 'data' => $stmt->fetchAll()];
}

function createComment($db, $data) {
    if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
        http_response_code(400);
        return ['success' => false];
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id=?");
    $check->execute([$data['resource_id']]);

    if (!$check->fetch()) {
        http_response_code(404);
        return ['success' => false];
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([
        $data['resource_id'],
        $data['author'],
        $data['text']
    ]);

    http_response_code(201);
    return ['success' => true, 'id' => $db->lastInsertId()];
}

function deleteComment($db, $id) {
    if (!$id) {
        http_response_code(400);
        return ['success' => false];
    }

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        return ['success' => false];
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    return ['success' => true];
}
