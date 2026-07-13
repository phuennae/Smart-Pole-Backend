<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';

$user = $_POST['username'] ?? '';
$pass = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';

if ($user != '' && $pass != '') {
    // 💡 เข้ารหัสผ่านแบบ bcrypt
    $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
    
    // แปลง Role ให้เป็นตัวพิมพ์เล็ก (admin / user) ให้ตรงกับ DB
    $role = strtolower($role);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$user, $hashed_pass, $role]);
        echo json_encode(["status" => "success", "message" => "เพิ่มผู้ใช้สำเร็จ"]);
    } catch (Exception $e) {
        // ดัก Error เผื่อชื่อซ้ำ
        echo json_encode(["status" => "error", "message" => "ชื่อนี้อาจมีอยู่แล้ว"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน"]);
}
?>