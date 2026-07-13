<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'config.php';

$id = $_GET['id'] ?? 0;

// 🔥 ดึง tb_device_id ออกมาจากฐานข้อมูลเพิ่มด้วย
$stmt = $pdo->prepare("SELECT ip_address, port, tb_device_id FROM nodes WHERE id = ?");
$stmt->execute([$id]);
$node = $stmt->fetch();

if ($node) {
    $ip = $node['ip_address'];
    $port = $node['port'] ?: 80;
    
    // เก็บค่า Device ID ของ ThingsBoard
    $tb_device_id = $node['tb_device_id']; 
    
    // ตั้งค่า Timeout ป้องกันเว็บค้าง
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);

    // 1. เช็คสถานะทั่วไป (Check Online & Song)
    $st = checkStatus($ip, $port); 
    
    $voltage = '-';
    $current = '-';
    $power = '-';
    $energy = '-';
    $percent = 0;

    if ($st) {
        // 2. ถ้า Online ค่อยไปดึงค่า PZEM (มิเตอร์วัดไฟ)
        $pzem_url = "http://$ip:$port/pzem_data";
        $pzem_json = @file_get_contents($pzem_url, false, $ctx);
        
        if ($pzem_json) {
            $pzem_data = json_decode($pzem_json, true);
            if (isset($pzem_data['v'])) {
                // จัดรูปแบบตัวเลขให้สวยงาม
                $voltage = number_format(floatval($pzem_data['v']), 1);
                $current = isset($pzem_data['c']) ? number_format(floatval($pzem_data['c']), 2) : '-';
                $power = isset($pzem_data['p']) ? number_format(floatval($pzem_data['p']), 1) : '-';
                $energy = isset($pzem_data['e']) ? number_format(floatval($pzem_data['e']), 1) : '-';
                
                // สูตรแบต 12V
                $v_float = floatval($pzem_data['v']);
                $percent = (($v_float - 11.0) / (12.6 - 11.0)) * 100;
                $percent = max(0, min(100, $percent));
            }
        }
    }

    // ส่งออกเป็น JSON ให้ React
    echo json_encode([
        'status' => 'success',
        'online' => $st ? true : false,
        'tb_device_id' => $tb_device_id, // 🔥 ส่งค่านี้กลับไปให้ React
        'song' => ($st && isset($st['song'])) ? $st['song'] : "",
        'data' => [
            'voltage' => $voltage,
            'current' => $current,
            'power' => $power,
            'energy' => $energy,
            'battery_pct' => round($percent)
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'online' => false, 'message' => 'Node ID not found']);
}
?>