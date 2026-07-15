<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';
date_default_timezone_set('Asia/Bangkok');

$id = isset($_POST['id']) ? $_POST['id'] : '';
$token = isset($_POST['token']) ? $_POST['token'] : '';

if (!$id || !$token) {
    echo json_encode(['valid' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT session_token, last_active FROM users WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['session_token'] === $token) {
    
    $last_active = !empty($row['last_active']) ? strtotime($row['last_active']) : 0;
    $now = time();

    if (($now - $last_active) > 1800) {
        echo json_encode(['valid' => false]);
    } else {
        $now_str = date('Y-m-d H:i:s');
        $update_stmt = $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?");
        $update_stmt->execute([$now_str, $id]);
        
        echo json_encode(['valid' => true]);
    }
    
} else {
    echo json_encode(['valid' => false]);
}
?>