<?php
require_once 'config.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
// ดึง IP และ Port มาจากฐานข้อมูล
$stmt = $pdo->prepare("SELECT ip_address, port FROM nodes WHERE id = ?");
$stmt->execute([$id]);
$node = $stmt->fetch();

if ($node) {
    // ส่งทั้ง IP และ Port เข้าไปเช็ค
    $st = checkStatus($node['ip_address'], $node['port']); 
    echo json_encode([
        'online' => $st ? true : false,
        'song' => ($st && isset($st['song'])) ? $st['song'] : ""
    ]);
} else {
    echo json_encode(['online' => false, 'song' => '']);
}
?>