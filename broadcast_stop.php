<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
include 'config.php';

$selected_nodes = isset($_POST['nodes']) ? json_decode($_POST['nodes'], true) : [];

try {
    if (!empty($selected_nodes)) {
        $inQuery = implode(',', array_fill(0, count($selected_nodes), '?'));
        $stmt = $pdo->prepare("SELECT ip_address, port FROM nodes WHERE id IN ($inQuery)");
        $stmt->execute($selected_nodes);
    } else {
        $stmt = $pdo->query("SELECT ip_address, port FROM nodes");
    }
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($nodes as $node) {
        $ip = $node['ip_address'];
        $port = $node['port'] ?? 80;
        $url = "http://{$ip}:{$port}/stop";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
        curl_exec($ch);
        curl_close($ch);
    }
    
    echo json_encode(["status" => "success", "message" => "สั่งหยุดเสร็จสิ้น"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>