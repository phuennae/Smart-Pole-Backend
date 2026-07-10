<?php
require_once 'config.php'; 

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

// รับค่า action จากทั้ง GET และ POST (สำคัญมาก!)
$action = $_REQUEST['action'] ?? '';

// ดึงข้อมูล ESP32 ทั้งหมดเตรียมไว้
$stmt = $pdo->query("SELECT ip_address, port FROM nodes");
$nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ส่วนที่ 1: การประกาศสด (Live Stream)
 * ต้องทำก่อนเงื่อนไขอื่น และห้ามมีการ Redirect (header: location)
 */
if ($action == 'play_live') {
    // URL ของ Stream Server (Node.js)
    $stream_url = "http://theoneiot.i234.me:3000/stream"; 

    foreach ($nodes as $node) {
        $ip = $node['ip_address'];
        $port = $node['port'] ?? 80;
        
        // ส่งคำสั่งปลุก ESP32 พร้อมแนบ URL
        $target = "http://{$ip}:{$port}/play_live?url=" . urlencode($stream_url);
        
        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500); // รอเชื่อมต่อ 0.5 วิ
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);        // รอทำงาน 1 วิ
        curl_exec($ch);
        curl_close($ch);
    }
    echo "LiveStarted_OK"; // ตอบกลับ AJAX
    exit; // จบการทำงานทันที ไม่ต้องไปบรรทัดอื่น
}

/**
 * ส่วนที่ 2: การสั่งหยุด (Stop)
 * แยกออกมาให้ทำงานอิสระ
 */
if ($action == 'stop') {
    foreach ($nodes as $node) {
        $ip = $node['ip_address'];
        $port = $node['port'] ?? 80;
        
        $target = "http://{$ip}:{$port}/stop";
        
        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
        curl_exec($ch);
        curl_close($ch);
    }
    
    // ถ้าส่งมาจากปุ่มพูดสด (AJAX) ให้ exit เลย
    if (isset($_GET['action'])) {
        echo "Stop_OK";
        exit;
    }
    // ถ้าส่งมาจาก Dashboard ปกติ ให้ Redirect
    header("Location: index.php?msg=BroadcastStopSuccess");
    exit;
}

/**
 * ส่วนที่ 3: คำสั่งอื่นๆ (Play File, Volume)
 */
if ($action == 'play') {
    $file = $_POST['filename'] ?? '';
    if ($file !== "" && $file[0] !== '/') $file = '/' . $file;
    foreach ($nodes as $node) {
        $target = "http://{$node['ip_address']}:{$node['port']}/play?path=" . urlencode($file);
        file_get_contents($target); // ใช้แบบง่าย
    }
    header("Location: index.php?msg=BroadcastPlaySuccess");
    exit;
}

if ($action == 'vol') {
    $vol = (int)$_POST['volume'];
    foreach ($nodes as $node) {
        $target = "http://{$node['ip_address']}:{$node['port']}/vol?v=" . $vol;
        file_get_contents($target);
    }
    header("Location: index.php?msg=BroadcastVolumeSuccess");
    exit;
}

// ถ้าไม่มี action ตรงเลย ให้กลับหน้าหลัก
header("Location: index.php");
exit;