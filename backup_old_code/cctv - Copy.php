<?php
session_start();
require_once 'config.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';

// 2. ดึงข้อมูลกล้องจาก Database
try {
    $stmt = $pdo->query("SELECT * FROM cameras ORDER BY id DESC");
    $cameras = $stmt->fetchAll();
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบ CCTV - Smart Pole Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Kanit', sans-serif; }
        .camera-card { border: none; border-radius: 12px; overflow: hidden; background: #fff; transition: 0.3s; }
        .camera-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        
        /* สัดส่วนวิดีโอ 16:9 */
        .video-wrapper { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; display: flex; align-items: center; justify-content: center; }
        .video-wrapper iframe { width: 100%; height: 100%; border: none; z-index: 1; }
        
        /* ส่วนแสดงจอดำ/Placeholder เมื่อ iframe โดนบล็อก */
        .video-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #666; z-index: 0; }
        
        .cam-overlay { position: absolute; top: 10px; left: 10px; z-index: 10; background: rgba(0,0,0,0.6); color: #fff; padding: 2px 12px; border-radius: 4px; font-size: 0.85rem; pointer-events: none; }
        .cam-controls { position: absolute; bottom: 10px; right: 10px; z-index: 20; display: flex; gap: 8px; }
        .btn-cam { padding: 6px 10px; font-size: 0.9rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-video text-danger me-2"></i> ระบบกล้องวงจรปิด Smart City</h3>
        <?php if ($role == 'admin'): ?>
            <button class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#addCamModal">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มกล้องใหม่
            </button>
        <?php endif; ?>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php if (count($cameras) > 0): ?>
            <?php foreach ($cameras as $cam): ?>
            <div class="col">
                <div class="card camera-card shadow-sm h-100">
   <div class="video-wrapper">
    <div class="cam-overlay">
        <i class="fas fa-circle text-danger me-1 small blink"></i> LIVE: <?= htmlspecialchars($cam['camera_name']) ?>
    </div>
    
    <img src="<?= htmlspecialchars($cam['ip_address']) ?>" 
         id="cam-img-<?= $cam['id'] ?>"
         class="img-fluid" 
         style="width: 100%; height: 100%; object-fit: cover;"
         onerror="this.src='https://via.placeholder.com/640x360?text=Camera+Offline+หรือ+IP+ผิดพลาด'">

    <div class="cam-controls">
        <a href="<?= htmlspecialchars($cam['ip_address']) ?>" target="_blank" class="btn btn-dark btn-cam border-light shadow" title="ขยายเต็มจอ">
            <i class="fas fa-expand-alt"></i>
        </a>
        
        <?php if ($role == 'admin'): ?>
            <button type="button" class="btn btn-warning btn-cam shadow" 
                    onclick='openEditModal(<?= json_encode($cam) ?>)'>
                <i class="fas fa-edit"></i>
            </button>
            <a href="process_camera.php?action=delete&id=<?= $cam['id'] ?>" class="btn btn-danger btn-cam shadow" onclick="return confirm('ยืนยันการลบกล้องนี้?')">
                <i class="fas fa-trash-alt"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
// สั่งให้รูปภาพอัปเดตตัวเองทุก 1-2 วินาทีเพื่อให้ดูเคลื่อนไหว (กรณี CGI ไม่ส่ง Stream มา)
setInterval(function(){
    var img = document.getElementById('cam-img-<?= $cam['id'] ?>');
    if(img){
        var src = img.src.split('?')[0]; // ตัด Token เก่าออก
        img.src = src + '?t=' + new Date().getTime(); // เพิ่ม Parameter ใหม่ป้องกัน Cache
    }
}, 2000); // อัปเดตทุก 2 วินาที
</script>

                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?= htmlspecialchars($cam['camera_name']) ?></span>
                        </div>
                        <p class="text-muted small mb-0"><i class="fas fa-map-marker-alt text-primary me-1"></i> <?= htmlspecialchars($cam['location'] ?: 'ไม่ระบุพิกัด') ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-camera fa-3x text-light mb-3"></i>
                <p class="text-muted">ไม่พบข้อมูลกล้องในระบบ</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addCamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="process_camera.php?action=add" method="POST" class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>เพิ่มจุดติดตั้งกล้อง</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อเรียกกล้อง</label>
                    <input type="text" name="name" class="form-control" placeholder="เช่น กล้องแยกอโศก" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">URL วิดีโอ (จาก BMA Traffic)</label>
                    <input type="text" name="ip" class="form-control" placeholder="http://www.bmatraffic.com/..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ตำแหน่งติดตั้ง</label>
                    <input type="text" name="location" class="form-control" placeholder="ระบุเลขเสา หรือ ชื่อถนน">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-success px-4">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editCamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="process_camera.php?action=edit" method="POST" class="modal-content shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลกล้อง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อเรียกกล้อง</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">URL วิดีโอ</label>
                    <input type="text" name="ip" id="edit_ip" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ตำแหน่งติดตั้ง</label>
                    <input type="text" name="location" id="edit_location" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-warning px-4 fw-bold">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * ฟังก์ชันเปิด Modal แก้ไขและเติมข้อมูล
 * ใช้รับค่าเป็น Object จาก JSON เพื่อความแม่นยำ
 */
function openEditModal(camData) {
    // เติมข้อมูลลงในฟิลด์ต่างๆ ของ Modal
    document.getElementById('edit_id').value = camData.id;
    document.getElementById('edit_name').value = camData.camera_name;
    document.getElementById('edit_ip').value = camData.ip_address;
    document.getElementById('edit_location').value = camData.location;
    
    // สั่งแสดง Modal
    var myModal = new bootstrap.Modal(document.getElementById('editCamModal'));
    myModal.show();
}
</script>

</body>
</html>