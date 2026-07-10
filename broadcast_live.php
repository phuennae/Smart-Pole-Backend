<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
include 'config.php';

// รับรายการเสาที่เลือก (ถ้าไม่ส่งมาแปลว่าเลือกทั้งหมด)
$selected_nodes = isset($_POST['nodes']) ? json_decode($_POST['nodes'], true) : [];

// URL Stream Server กลางของซัพพลายเออร์ (เอาไว้แก้ทีหลังได้)
$stream_url = "http://theoneiot.i234.me:3000/stream"; 

try {
    if (!empty($selected_nodes)) {
        // ดึง IP เฉพาะเสาที่เลือก
        $inQuery = implode(',', array_fill(0, count($selected_nodes), '?'));
        $stmt = $pdo->prepare("SELECT ip_address, port FROM nodes WHERE id IN ($inQuery)");
        $stmt->execute($selected_nodes);
    } else {
        // ดึง IP ทุกเสา
        $stmt = $pdo->query("SELECT ip_address, port FROM nodes");
    }
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ยิงคำสั่งไปหาเสาไฟทุกต้นอย่างรวดเร็ว
    foreach ($nodes as $node) {
        $ip = $node['ip_address'];
        $port = $node['port'] ?? 80;
        $url = "http://{$ip}:{$port}/play_live?url=" . urlencode($stream_url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500); // แค่ส่งคำสั่ง ไม่ต้องรอนาน
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
        curl_exec($ch);
        curl_close($ch);
    }
    
    echo json_encode(["status" => "success", "message" => "เริ่มดึงสัญญาณสดแล้ว"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>