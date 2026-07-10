<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
require_once 'config.php';

// ปลดล็อค Session ออก เพื่อให้ React ยิง API มาจัดการข้อมูลได้โดยตรง
$action = $_REQUEST['action'] ?? '';

if ($action == 'add') {
    $name = $_POST['name'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $ptz_ip = $_POST['ptz_ip'] ?? null;
    $ptz_port = $_POST['ptz_port'] ?? 80;
    $ptz_username = $_POST['ptz_username'] ?? 'admin';
    $ptz_password = $_POST['ptz_password'] ?? '';
    $location = $_POST['location'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cameras 
            (camera_name, ip_address, ptz_ip, ptz_port, ptz_username, ptz_password, location) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $ip, $ptz_ip, $ptz_port, $ptz_username, $ptz_password, $location]);
        
        echo json_encode(["status" => "success", "message" => "เพิ่มกล้องสำเร็จ"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    
} elseif ($action == 'edit') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $ptz_ip = $_POST['ptz_ip'] ?? null;
    $ptz_port = $_POST['ptz_port'] ?? 80;
    $ptz_username = $_POST['ptz_username'] ?? 'admin';
    $ptz_password = $_POST['ptz_password'] ?? '';
    $location = $_POST['location'] ?? '';
    
    try {
        if (empty($ptz_password)) {
            $stmt = $pdo->prepare("UPDATE cameras 
                SET camera_name=?, ip_address=?, ptz_ip=?, ptz_port=?, ptz_username=?, location=? 
                WHERE id=?");
            $stmt->execute([$name, $ip, $ptz_ip, $ptz_port, $ptz_username, $location, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE cameras 
                SET camera_name=?, ip_address=?, ptz_ip=?, ptz_port=?, ptz_username=?, ptz_password=?, location=? 
                WHERE id=?");
            $stmt->execute([$name, $ip, $ptz_ip, $ptz_port, $ptz_username, $ptz_password, $location, $id]);
        }
        
        echo json_encode(["status" => "success", "message" => "แก้ไขข้อมูลกล้องสำเร็จ"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    
} elseif ($action == 'delete') {
    $id = $_REQUEST['id'] ?? 0;
    try {
        $stmt = $pdo->prepare("DELETE FROM cameras WHERE id=?");
        $stmt->execute([$id]);
        
        echo json_encode(["status" => "success", "message" => "ลบกล้องสำเร็จ"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่พบคำสั่ง Action ที่ระบุ"]);
}
?>