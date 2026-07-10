<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php'; // เรียกใช้การเชื่อมต่อ PDO ของซัพพลายเออร์

$user = isset($_POST['username']) ? $_POST['username'] : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';

// ค้นหา User ใน MariaDB
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$user]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบรหัสผ่าน (ใช้ password_verify เทียบกับค่า Hash ใน DB)
if ($user_data && password_verify($pass, $user_data['password'])) {
    echo json_encode([
        "status" => "success", 
        "user" => [
            "id" => $user_data['id'],
            "username" => $user_data['username'],
            "role" => $user_data['role']
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"]);
}
?>