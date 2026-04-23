<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

/* ===== Database Connection (بدل Database.php) ===== */

try {

$db = new PDO(
"mysql:host=localhost;dbname=course_resources",
"root",
""
);

$db->setAttribute(
PDO::ATTR_ERRMODE,
PDO::ERRMODE_EXCEPTION
);

$db->exec("SET NAMES utf8");

}

catch(PDOException $e){

echo json_encode([
"success"=>false,
"message"=>"Database connection failed"
]);

exit;
}

/* ===== Functions ===== */

function sendResponse($data,$status=200){

http_response_code($status);

echo json_encode($data);

exit;
}

function validateUrl($url){

return filter_var(
$url,
FILTER_VALIDATE_URL
)!==false;
}

function sanitizeInput($value){

return htmlspecialchars(
strip_tags(
trim((string)$value)
),
ENT_QUOTES,
'UTF-8'
);

}

function validateRequiredFields($data,$fields){

$missing=[];

foreach($fields as $field){

if(
!isset($data[$field]) ||
trim((string)$data[$field])===''
){

$missing[]=$field;

}

}

return [
'valid'=>count($missing)===0,
'missing'=>$missing
];

}

/* ===== Inputs ===== */

$method=$_SERVER['REQUEST_METHOD'];

$rawData=file_get_contents(
'php://input'
);

$data=json_decode(
$rawData,
true
);

if(!is_array($data)){

$data=[];

}

$action=$_GET['action'] ?? null;
$id=$_GET['id'] ?? null;
$resource_id=$_GET['resource_id'] ?? null;


/* ========= GET ALL RESOURCES ========= */

if(
$method==='GET' &&
!$id &&
$action!=='comments'
){

$stmt=$db->prepare(
"SELECT id,title,description,link,created_at
FROM resources
ORDER BY created_at DESC"
);

$stmt->execute();

$resources=$stmt->fetchAll(
PDO::FETCH_ASSOC
);

sendResponse([
"success"=>true,
"data"=>$resources
]);

}


/* ========= GET ONE RESOURCE ========= */

if(
$method==='GET' &&
$id
){

$stmt=$db->prepare(
"SELECT id,title,description,link,created_at
FROM resources
WHERE id=?"
);

$stmt->execute([
(int)$id
]);

$row=$stmt->fetch(
PDO::FETCH_ASSOC
);

if(!$row){

sendResponse([
"success"=>false
],404);

}

sendResponse([
"success"=>true,
"data"=>$row
]);

}


/* ========= CREATE RESOURCE ========= */

if(
$method==='POST' &&
$action!=='comment'
){

$check=validateRequiredFields(
$data,
['title','link']
);

if(!$check['valid']){

sendResponse([
"success"=>false
],400);

}

$title=sanitizeInput(
$data['title']
);

$description=sanitizeInput(
$data['description'] ?? ''
);

$link=trim(
$data['link']
);

if(
!validateUrl($link)
){

sendResponse([
"success"=>false
],400);

}

$stmt=$db->prepare(
"INSERT INTO resources
(title,description,link)
VALUES(?,?,?)"
);

$stmt->execute([
$title,
$description,
$link
]);

sendResponse([
"success"=>true,
"id"=>$db->lastInsertId()
],201);

}


/* ========= UPDATE ========= */

if(
$method==='PUT'
){

if(
!isset($data['id'])
){

sendResponse([
"success"=>false
],400);

}

$id=(int)$data['id'];

$stmt=$db->prepare(
"UPDATE resources
SET title=?,
description=?,
link=?
WHERE id=?"
);

$stmt->execute([

sanitizeInput($data['title']),
sanitizeInput(
$data['description'] ?? ''
),
trim($data['link']),
$id

]);

sendResponse([
"success"=>true
]);

}


/* ========= DELETE ========= */

if(
$method==='DELETE'
){

if(!$id){

sendResponse([
"success"=>false
],400);

}

$stmt=$db->prepare(
"DELETE FROM resources
WHERE id=?"
);

$stmt->execute([
(int)$id
]);

sendResponse([
"success"=>true
]);

}


/* ========= GET COMMENTS ========= */

if(
$method==='GET' &&
$action==='comments'
){

$stmt=$db->prepare(
"SELECT id,resource_id,author,text,created_at
FROM comments_resource
WHERE resource_id=?
ORDER BY created_at ASC"
);

$stmt->execute([
(int)$resource_id
]);

$comments=$stmt->fetchAll(
PDO::FETCH_ASSOC
);

sendResponse([
"success"=>true,
"data"=>$comments
]);

}


/* ========= ADD COMMENT ========= */

if(
$method==='POST' &&
$action==='comment'
){

$stmt=$db->prepare(
"INSERT INTO comments_resource
(resource_id,author,text)
VALUES(?,?,?)"
);

$stmt->execute([

(int)$data['resource_id'],
sanitizeInput(
$data['author']
),
sanitizeInput(
$data['text']
)

]);

sendResponse([
"success"=>true,
"id"=>$db->lastInsertId()
],201);

}


sendResponse([
"success"=>false,
"message"=>"Method Not Allowed"
],405);

?>