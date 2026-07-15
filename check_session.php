<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';

$id = isset($_POST['id']) ? $_POST['id'] : '';
$token = isset($_POST['token']) ? $_POST['token'] : '';

if (!$id || !$token) {
    echo json_encode(['valid' => false]);
    exit;
}

// ตรวจสอบ Token ด้วยระบบ PDO
$stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['session_token'] === $token) {
    echo json_encode(['valid' => true]); // ตั๋วตรง ยังใช้งานได้
} else {
    echo json_encode(['valid' => false]); // ตั๋วไม่ตรง (โดนคนอื่นซ้อน)
}
?>