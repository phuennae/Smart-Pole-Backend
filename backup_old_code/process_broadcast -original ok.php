<?php
// 1. ตรวจสอบการเรียกใช้ไฟล์ config.php
if (!file_exists('config.php')) {
    die("Error: ไม่พบไฟล์ config.php ในระบบ");
}
require_once 'config.php'; 

// 2. ตรวจสอบ Login (Session ควรถูกเริ่มใน config.php แล้ว)
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized: กรุณาเข้าสู่ระบบ");
}

$msg = "";

/**
 * ฟังก์ชันกลางสำหรับส่งคำสั่ง HTTP GET ไปยัง ESP32
 */
function sendToESP($ip, $path, $port = 80) {
    $url = "http://{$ip}:{$port}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // รอนานขึ้นนิดนึงป้องกัน Time out
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $res, 'code' => $http_code);
}

// --- ส่วนที่ 1: จัดการคำสั่งแบบ Single (ส่งผ่าน URL / GET) ---

// 1.1 สั่งเล่นเพลงรายเครื่อง
if (isset($_GET['action']) && $_GET['action'] == 'play_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $file = $_GET['file'] ?? '';

    if (!empty($ip) && !empty($file)) {
        // เติม / ข้างหน้าถ้าไม่มี
        if ($file[0] !== '/') $file = '/' . $file;
        sendToESP($ip, "/play?path=" . urlencode($file), $port);
        $msg = "สั่งเล่นไฟล์ {$file} สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 1.2 สั่งหยุดเพลงรายเครื่อง
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

// 1.3 สั่งลบไฟล์รายเครื่อง
if (isset($_GET['action']) && $_GET['action'] == 'delete_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $file = $_GET['file'] ?? '';

    if (!empty($ip) && !empty($file)) {
        sendToESP($ip, "/delete?path=" . urlencode($file), $port);
        $msg = "ลบไฟล์สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 1.4 ส่วนจัดการอัปโหลดไฟล์ (รองรับทั้ง AJAX และ Form ปกติ)
if (isset($_GET['action']) && $_GET['action'] == 'upload_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    
    if (!empty($ip) && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
        $file_path = $_FILES['fileToUpload']['tmp_name'];
        $file_name = $_FILES['fileToUpload']['name'];
        $file_type = $_FILES['fileToUpload']['type'];
        
        $url = "http://{$ip}:{$port}/upload";
        $ch = curl_init($url);
        
        // เตรียมไฟล์ส่งแบบ Multipart
        $cfile = new CURLFile($file_path, $file_type, $file_name);
        $data = array('file' => $cfile); 
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 นาที สำหรับไฟล์เพลงขนาดใหญ่
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // ตรวจสอบว่าเป็น AJAX Request หรือไม่
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            if ($http_code == 200) {
                echo "success";
            } else {
                echo "failed_code_" . $http_code;
            }
            exit;
        } else {
            $msg = ($http_code == 200) ? "UploadFinished" : "UploadError_Code_" . $http_code;
            header("Location: index.php?msg=" . urlencode($msg));
            exit;
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { echo "no_file"; exit; }
        header("Location: index.php?msg=NoFileSelected");
        exit;
    }
}

// --- ส่วนที่ 2: จัดการคำสั่งแบบ Broadcast (ส่งผ่าน Form / POST) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ดึงข้อมูลโหนดทั้งหมดเพื่อยิงคำสั่งวนลูป
    $stmt = $pdo->query("SELECT ip_address, port FROM nodes");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($action == 'play') {
        $file = $_POST['filename'] ?? '';
        if ($file !== "" && $file[0] !== '/') $file = '/' . $file;
        
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/play?path=" . urlencode($file), $node['port']);
        }
        $msg = "BroadcastPlaySuccess";
    } 
    elseif ($action == 'stop') {
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/stop", $node['port']);
        }
        $msg = "BroadcastStopSuccess";
    }
    elseif ($action == 'vol') {
        $vol = (int)$_POST['volume'];
        foreach ($nodes as $node) {
            sendToESP($node['ip_address'], "/vol?v=" . $vol, $node['port']);
        }
        $msg = "BroadcastVolumeSuccess";
    }

    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// ถ้าหลุดมาถึงนี่ ให้ดีดกลับหน้าหลัก
header("Location: index.php");
exit;