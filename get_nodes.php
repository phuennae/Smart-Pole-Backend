<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'config.php';

$nodes = $pdo->query("SELECT * FROM nodes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$formatted_nodes = array();
foreach ($nodes as $row) {
    // ใช้ ?? 0 เพื่อกำหนดค่าเริ่มต้นเป็น 0 ถ้าใน DB เป็น NULL
    $formatted_nodes[] = [
        "id"          => $row['id'],
        "name"        => $row['name'] ?? 'No Name',
        "ip_address"  => $row['ip_address'] ?? '',
        "port"        => $row['port'] ?? '80',
        "lat"         => (float)($row['latitude'] ?? 0),
        "lng"         => (float)($row['longitude'] ?? 0),
        "last_volume" => $row['last_volume'] ?? 80, 
        "status"      => "online"
    ];
}

echo json_encode($formatted_nodes);
?>