<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';
$id = isset($_POST['id']) ? $_POST['id'] : '';

if ($id) {
    // ล้าง token และเวลาทิ้ง
    $stmt = $pdo->prepare("UPDATE users SET session_token = NULL, last_active = NULL WHERE id = ?");
    $stmt->execute([$id]);
}
echo json_encode(['status' => 'success']);
?>