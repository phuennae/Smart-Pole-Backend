<?php
// 1. ตรวจสอบไฟล์ config และ Session
if (!file_exists('config.php')) {
    die("Error: ไม่พบไฟล์ config.php ในระบบ");
}
require_once 'config.php'; 

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized: กรุณาเข้าสู่ระบบ");
}

// รับค่า action จากทั้ง GET และ POST
$action = $_REQUEST['action'] ?? '';
$msg = "";

// ดึงข้อมูล ESP32 ทั้งหมดจากฐานข้อมูลเตรียมไว้
$stmt = $pdo->query("SELECT ip_address, port FROM nodes");
$nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ฟังก์ชันกลางสำหรับส่งคำสั่ง HTTP GET ไปยัง ESP32
 */
function sendToESP($ip, $path, $port = 80, $timeout = 1) {
    $url = "http://{$ip}:{$port}{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500); 
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $res, 'code' => $http_code);
}

// --- ส่วนที่ 1: การประกาศสด (Live Stream) - สำคัญที่สุด ---
if ($action == 'play_live') {
    // URL ของ Stream Server (Node.js)
    $stream_url = "http://theoneiot.i234.me:3000/stream"; 

    foreach ($nodes as $node) {
        $ip = $node['ip_address'];
        $port = $node['port'] ?? 80;
        // ส่งคำสั่งพร้อมแนบ URL สตรีม
        sendToESP($ip, "/play_live?url=" . urlencode($stream_url), $port);
    }
    echo "LiveStarted_OK"; // ตอบกลับ AJAX ของปุ่มพูด
    exit;
}

// --- ส่วนที่ 2: จัดการคำสั่งรายเครื่อง (Single Action) ---

// 2.1 เล่นเพลงรายเครื่อง
if ($action == 'play_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $file = $_GET['file'] ?? '';
    if (!empty($ip) && !empty($file)) {
        if ($file[0] !== '/') $file = '/' . $file;
        sendToESP($ip, "/play?path=" . urlencode($file), $port, 5);
        $msg = "สั่งเล่นไฟล์ {$file} สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 2.2 หยุดเพลงรายเครื่อง
if ($action == 'stop_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    if (!empty($ip)) {
        sendToESP($ip, "/stop", $port, 2);
        $msg = "สั่งหยุดเพลงเรียบร้อย";
    }
    //header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 2.3 ลบไฟล์รายเครื่อง
if ($action == 'delete_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $file = $_GET['file'] ?? '';
    if (!empty($ip) && !empty($file)) {
        sendToESP($ip, "/delete?path=" . urlencode($file), $port, 5);
        $msg = "ลบไฟล์สำเร็จ";
    }
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 2.4 อัปโหลดไฟล์ไปยัง ESP32
if ($action == 'upload_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    
    if (!empty($ip) && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
        $url = "http://{$ip}:{$port}/upload";
        $ch = curl_init($url);
        $cfile = new CURLFile($_FILES['fileToUpload']['tmp_name'], $_FILES['fileToUpload']['type'], $_FILES['fileToUpload']['name']);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => $cfile));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); 
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo ($http_code == 200) ? "success" : "failed_" . $http_code;
            exit;
        } else {
            header("Location: index.php?msg=UploadFinished");
            exit;
        }
    }
}

// --- ส่วนที่ 3: จัดการคำสั่งแบบกลุ่ม (Broadcast) ---

if ($action == 'play') {
    $file = $_POST['filename'] ?? '';
    if ($file !== "" && $file[0] !== '/') $file = '/' . $file;
    foreach ($nodes as $node) {
        sendToESP($node['ip_address'], "/play?path=" . urlencode($file), $node['port'], 2);
    }
    header("Location: index.php?msg=BroadcastPlaySuccess");
    exit;
}

// เพิ่มใน process_broadcast.php
if ($action == 'play_live_selected') {
    $nodes_to_play = json_decode($_POST['nodes'], true);
    $stream_url = "http://theoneiot.i234.me:3000/stream"; 

    if (is_array($nodes_to_play)) {
        foreach ($nodes_to_play as $node) {
            $ip = $node['ip'];
            $port = $node['port'] ?? 80;
			
			
			// ==========================================
            // 1. เพิ่มส่วนการดึงค่า Volume และส่งไปตั้งค่าที่ ESP32
            // ==========================================
            if (isset($node['vol'])) {
                // แปลงค่า 0-100% ให้เป็น 0-21
                $espVol = round(((int)$node['vol'] * 21) / 100);
                
                // ใช้ฟังก์ชัน sendToESP ที่มีอยู่แล้ว ยิงไปเซ็ตเสียง (ให้เวลา 1 วินาที)
                sendToESP($ip, "/vol?v={$espVol}", $port, 1);
            }
            // ==========================================
			
			
            $target = "http://{$ip}:{$port}/play_live?url=" . urlencode($stream_url);
            
            $ch = curl_init($target);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
            curl_exec($ch);
            curl_close($ch);
        }
    }
    echo "SelectedNodesLiveStarted";
    exit;
}

//***************************************************
// --- เพิ่มส่วนนี้ใน process_broadcast.php ---

// สั่งเล่นไฟล์เสียงเฉพาะกลุ่มโหนดที่เลือก (Bulk File Play)
if ($action == 'play_selected') {
    $nodes_to_play = json_decode($_POST['nodes'], true);
    $filename = $_POST['filename'] ?? '';

    // ตรวจสอบชื่อไฟล์ให้มี / นำหน้า
    if ($filename !== "" && $filename[0] !== '/') {
        $filename = '/' . $filename;
    }

    if (is_array($nodes_to_play) && !empty($filename)) {
        foreach ($nodes_to_play as $node) {
            $ip = $node['ip'];
            $port = $node['port'] ?? 80;
            
            // ใช้ Endpoint /play และ parameter path ตามมาตรฐานเดิมของคุณ
            $target = "http://{$ip}:{$port}/play?path=" . urlencode($filename);
            
            $ch = curl_init($target);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000); // เพิ่มเวลาเป็น 2 วินาทีสำหรับเปิดไฟล์ SD Card
            curl_exec($ch);
            curl_close($ch);
        }
        echo "SelectedNodesPlayFinished";
    } else {
        echo "Error: Missing nodes or filename";
    }
    exit;
}
//********************************************************************************

// เพิ่มส่วนนี้ในไฟล์ process_broadcast.php
if (isset($_POST['action']) && $_POST['action'] == 'stop_selected') {
    $nodes = json_decode($_POST['nodes'], true);
    if ($nodes) {
        foreach ($nodes as $node) {
            $ip = $node['ip'];
            $port = $node['port'] ?? 80;
            // เรียก URL /stop ของ ESP32
            $url = "http://{$ip}:{$port}/stop";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Timeout สั้นๆ เพื่อไม่ให้หน้าเว็บค้าง
            curl_exec($ch);
            curl_close($ch);
        }
    }
    echo "Nodes stopped successfully";
    exit;
}


if ($action == 'stop') {
    foreach ($nodes as $node) {
        sendToESP($node['ip_address'], "/stop", $node['port'], 2);
    }
    // ตรวจสอบว่าถ้ามาจาก AJAX (ปุ่มพูด) ให้ตอบข้อความแทนการ Redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo "Stop_OK";
        exit;
    }
    header("Location: index.php?msg=BroadcastStopSuccess");
    exit;
}

if ($action == 'vol') {
    $vol = (int)$_POST['volume'];
    foreach ($nodes as $node) {
        sendToESP($node['ip_address'], "/vol?v=" . $vol, $node['port'], 2);
    }
    header("Location: index.php?msg=BroadcastVolumeSuccess");
    exit;
}
// --- แก้ไขใน process_broadcast.php ---
// --- ใน process_broadcast.php ---

// ปรับเสียงรายเครื่อง และบันทึกลง DB
// 1. ตรวจสอบส่วนหยุดเพลง
if ($action == 'stop_single') {
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    if (!empty($ip)) {
        sendToESP($ip, "/stop", $port, 2);
    }
    echo "OK_Stopped"; 
    exit;
}

// 2. ตรวจสอบส่วนปรับเสียง (ต้องมี SQL Update)
// --- แก้ไขส่วน vol_single ใน process_broadcast.php ---
if ($action == 'vol_single') {
    $node_id = $_GET['id'] ?? ''; // รับ ID มาจาก JavaScript
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? 80;
    $vol = (int)($_GET['v'] ?? 11);
    
    if (!empty($node_id) && !empty($ip)) {
        // 1. สั่งงาน ESP32 โดยใช้ IP และ Port
        sendToESP($ip, "/vol?v=" . $vol, $port, 1);
        
        // 2. บันทึกลง Database โดยระบุด้วย ID (แม่นยำที่สุด)
        $stmt = $pdo->prepare("UPDATE nodes SET last_volume = ? WHERE id = ?");
        $stmt->execute([$vol, $node_id]);
    }
    echo "OK_ID_{$node_id}_VOL_{$vol}";
    exit;
}

// ถ้าไม่มี Action ตรงเลย ให้ดีดกลับหน้าหลัก
header("Location: index.php");
exit;