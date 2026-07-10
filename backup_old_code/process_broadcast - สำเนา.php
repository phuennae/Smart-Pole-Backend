<?php
// 1. ตรวจสอบการเรียกใช้ไฟล์ config.php
if (!file_exists('config.php')) {
    die("Error: ไม่พบไฟล์ config.php ในระบบ");
}
require_once 'config.php'; 

// 2. ตรวจสอบ Login (Session ถูกเริ่มแล้วใน config.php)
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized: กรุณาเข้าสู่ระบบ");
}

$msg = "";

// --- ส่วนที่ 1: จัดการคำสั่งแบบ Single (ส่งผ่าน URL / GET) ---
// ตรวจสอบ action: play_single
// แก้ไขส่วน play_single ใน process_broadcast.php
if (isset($_GET['action']) && $_GET['action'] == 'play_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80; // รับค่า port จาก GET
    $file = $_GET['file'] ?? '';

    if (!empty($ip) && !empty($file)) {
        // ส่งพอร์ตเข้าไปในฟังก์ชันด้วย
        $result = sendToESP($ip, "/play?path=" . urlencode($file), $port);
        $msg = "สั่งเล่นไฟล์สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// แก้ไขส่วน stop_single ให้รองรับพอร์ตเช่นกัน
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

// --- ส่วนจัดการลบไฟล์ ---
// เพิ่มส่วนนี้ใน process_broadcast.php
if (isset($_GET['action']) && $_GET['action'] == 'delete_single') {
    $ip = $_GET['ip'];
    $file = $_GET['file']; // เช่น /2.mp3
    
    // เรียกใช้ API delete ของ ESP32 โดยส่ง parameter 'path' [cite: 92]
    $url = "http://$ip/delete?path=" . urlencode($file);
    
    // ใช้ cURL หรือ file_get_contents เพื่อส่งคำสั่ง
    $result = @file_get_contents($url);
    
    // กลับไปยังหน้าหลักพร้อมข้อความแจ้งเตือน
    header("Location: index.php?msg=ลบไฟล์สำเร็จ");
    exit;
}

// --- ส่วนจัดการอัปโหลดไฟล์ ---
if (isset($_GET['action']) && $_GET['action'] == 'upload_single') {
    $ip = $_GET['ip'];
    
    if (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
        $file_path = $_FILES['fileToUpload']['tmp_name'];
        $file_name = $_FILES['fileToUpload']['name'];
        
        // ESP32 จะหยุดเล่นเพลงทันทีเมื่อเริ่มอัปโหลดเพื่อลดภาระ CPU [cite: 73]
        $ch = curl_init("http://$ip/upload");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($file_path, $_FILES['fileToUpload']['type'], $file_name)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // เผื่อเวลาสำหรับไฟล์ใหญ่
        
        $response = curl_exec($ch);
        curl_close($ch);
    }
    header("Location: index.php?msg=UploadFinished");
    exit;
}

// --- ส่วนที่ 2: จัดการคำสั่งแบบ Broadcast (ส่งผ่าน Form / POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // แก้ไข SQL ให้ดึงคอลัมน์ port มาด้วย
    $stmt = $pdo->query("SELECT ip_address, port FROM nodes");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($action == 'play') {
        $file = $_POST['filename'] ?? '';
        foreach ($nodes as $node) {
            // ส่งคำสั่งพร้อมพอร์ตของแต่ละ Node
            sendToESP($node['ip_address'], "/play?path=" . urlencode($file), $node['port']);
        }
    } 
    elseif ($action == 'stop') {
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/stop", $node['port']);
        }
    }
    elseif ($action == 'vol') {
        $vol = (int)$_POST['volume'];
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/vol?v=" . $vol, $node['port']);
        }
    }
    header("Location: index.php?msg=BroadcastSuccess");
    exit;
}

// ถ้าไม่มี action ใดๆ ให้กลับหน้าหลัก
header("Location: index.php");
exit;