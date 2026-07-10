<?php
require_once 'config.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- ส่วนการเพิ่ม Node ---
    if ($action == 'add') {
        $name = $_POST['name'];
        $ip = $_POST['ip'];
        $port = $_POST['port']; // 1. รับค่า port จากฟอร์มหน้า manage_nodes.php

        // 2. เพิ่มคอลัมน์ port ในคำสั่ง INSERT
        $stmt = $pdo->prepare("INSERT INTO nodes (name, ip_address, port) VALUES (?, ?, ?)");
        $stmt->execute([$name, $ip, $port]);
    }
    
    // --- ส่วนการแก้ไข Node ---
    if ($action == 'edit') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $ip = $_POST['ip'];
        $port = $_POST['port']; // 3. รับค่า port เพื่อใช้แก้ไข

        // 4. เพิ่มการ UPDATE คอลัมน์ port
        $stmt = $pdo->prepare("UPDATE nodes SET name = ?, ip_address = ?, port = ? WHERE id = ?");
        $stmt->execute([$name, $ip, $port, $id]);
    }
}

// --- ส่วนการลบ Node ---
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM nodes WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: manage_nodes.php");
exit;