<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'config.php';

$node_id = isset($_GET['node_id']) ? intval($_GET['node_id']) : 0;

if ($node_id > 0) {
    try {
        // ดึงข้อมูลเรียงตามเวลา (ชั่วโมง และ นาที)
        $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? ORDER BY hour, minute");
        $stmt->execute([$node_id]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ตัวแปลงตัวเลขวัน (day_of_week) ให้เป็นข้อความภาษาไทยให้ตรงกับหน้าเว็บ React
        $day_map = [
            0 => 'วันอาทิตย์',
            1 => 'วันจันทร์',
            2 => 'วันอังคาร',
            3 => 'วันพุธ',
            4 => 'วันพฤหัสบดี',
            5 => 'วันศุกร์',
            6 => 'วันเสาร์',
            7 => 'ทุกวัน',
            8 => 'วันทำงาน(จ-ศ)'
        ];

        $formatted = array();
        foreach ($schedules as $row) {
            // เติมเลข 0 ข้างหน้าถ้าชั่วโมง/นาทีเป็นเลขหลักเดียว (เช่น 8:0 กลายเป็น 08:00)
            $h = str_pad($row['hour'], 2, "0", STR_PAD_LEFT);
            $m = str_pad($row['minute'], 2, "0", STR_PAD_LEFT);
            
            $formatted[] = [
                'id' => $row['id'],
                'days' => isset($day_map[$row['day_of_week']]) ? $day_map[$row['day_of_week']] : 'ทุกวัน',
                'time' => $h . ':' . $m,
                'file' => $row['filename'],
                'volume' => $row['volume']
            ];
        }

        echo json_encode(["status" => "success", "data" => $formatted]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่พบรหัสอุปกรณ์"]);
}
?>