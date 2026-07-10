<?php
// 1. ตรวจสอบการเรียกใช้ไฟล์ config.php
if (!file_exists('config.php')) {
    die("Error: ไม่พบไฟล์ config.php ในระบบ");
}
require_once 'config.php'; 

// 2. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized: กรุณาเข้าสู่ระบบ");
}

$msg = "";

// ฟังก์ชันกลางสำหรับส่งคำสั่ง (GET)
function sendToESP($ip, $path, $port = 80) {
    $url = "http://{$ip}:{$port}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// --- ส่วนที่ 1: จัดการคำสั่งแบบ Single ---

if (isset($_GET['action']) && $_GET['action'] == 'play_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $file = $_GET['file'] ?? '';
    if (!empty($ip) && !empty($file)) {
        sendToESP($ip, "/play?path=" . urlencode($file), $port);
        $msg = "สั่งเล่นไฟล์สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'stop_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    if (!empty($ip)) {
        sendToESP($ip, "/stop", $port);
        $msg = "สั่งหยุดเพลงเรียบร้อย";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// --- ส่วนจัดการอัปโหลดไฟล์ (แก้ไขจุดนี้) ---
if (isset($_GET['action']) && $_GET['action'] == 'upload_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80; // เพิ่มการรับค่า Port
    
    if (!empty($ip) && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
        $file_path = $_FILES['fileToUpload']['tmp_name'];
        $file_name = $_FILES['fileToUpload']['name'];
        $file_type = $_FILES['fileToUpload']['type'];
        
        // กำหนด URL พร้อมพอร์ต
        $url = "http://{$ip}:{$port}/upload";
        
        $ch = curl_init($url);
        
        // เตรียมไฟล์สำหรับส่ง
        $cfile = new CURLFile($file_path, $file_type, $file_name);
        $data = array('file' => $cfile); // 'file' ต้องตรงกับชื่อฟิลด์ที่ WebServer ESP32 รอรับ
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // สำคัญ: ESP32 มักจะรับไฟล์ใหญ่ได้ช้า ต้องเพิ่ม Timeout ให้เพียงพอ
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); 
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $msg = "Upload Error: " . curl_error($ch);
        } else {
            $msg = ($http_code == 200) ? "UploadSuccess" : "UploadFailed_Code_$http_code";
        }
        curl_close($ch);
    } else {
        $msg = "NoFileSelected";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// --- ส่วนที่ 2: จัดการคำสั่งแบบ Broadcast (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stmt = $pdo->query("SELECT ip_address, port FROM nodes");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($action == 'play') {
        $file = $_POST['filename'] ?? '';
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/play?path=" . urlencode($file), $node['port']);
        }
    } 
    elseif ($action == 'stop') {
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/stop", $node['port']);
        }
    }
    header("Location: index.php?msg=BroadcastSuccess");
    exit;
}

header("Location: index.php");
exit;