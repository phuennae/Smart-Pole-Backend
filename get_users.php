<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'config.php';

$users = $pdo->query("SELECT id, username, role FROM users")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);
?>