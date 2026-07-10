<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$action = $_GET['action'] ?? '';

if ($action == 'add') {
    $name = $_POST['name'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $username = $_POST['username'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $location = $_POST['location'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cameras (camera_name, ip_address, username, password, location) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $ip, $username, $password, $location]);
        header("Location: index.php?msg=added");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    
} elseif ($action == 'edit') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $username = $_POST['username'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $location = $_POST['location'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE cameras SET camera_name=?, ip_address=?, username=?, password=?, location=? WHERE id=?");
        $stmt->execute([$name, $ip, $username, $password, $location, $id]);
        header("Location: index.php?msg=updated");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    
} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cameras WHERE id=?");
        $stmt->execute([$id]);
        header("Location: index.php?msg=deleted");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}