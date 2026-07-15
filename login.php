<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php'; // เรียกใช้การเชื่อมต่อ PDO

$user = isset($_POST['username']) ? $_POST['username'] : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';

// ค้นหา User ใน MariaDB
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$user]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบรหัสผ่าน (ใช้ password_verify เทียบกับค่า Hash ใน DB)
if ($user_data && password_verify($pass, $user_data['password'])) {
    
    // 1. สร้าง Token สุ่มใหม่
    $session_token = bin2hex(random_bytes(16)); 
    
    // 2. อัปเดต Token นี้ลงฐานข้อมูล (ปรับมาใช้ PDO ให้ตรงกับระบบของคุณ)
    $update_stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $update_stmt->execute([$session_token, $user_data['id']]);

    // 3. ส่งข้อมูลและ Token กลับไปให้ Frontend
    echo json_encode([
        "status" => "success", 
        "user" => [
            "id" => $user_data['id'],
            "username" => $user_data['username'],
            "role" => $user_data['role']
        ],
        "session_token" => $session_token
    ]);

} else {
    // ถ้ารหัสผิด
    echo json_encode([
        "status" => "error", 
        "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"
    ]);
}
?>