<?php
require_once 'config.php';
// ดึงข้อมูล nodes ทั้งหมด โดยสมมติว่าคุณได้เพิ่มคอลัมน์ port ใน DB แล้ว
$nodes = $pdo->query("SELECT * FROM nodes ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>จัดการ Node</title>
</head>
<body class="bg-light">

<?php include 'navbar.php'; // รวมเมนูนำทาง ?>

<div class="container py-4">
    <div class="d-flex justify-content-between mb-4">
        <h4><i class="fas fa-server"></i> จัดการรายชื่อลำโพง (Nodes)</h4>
        <a href="index.php" class="btn btn-secondary btn-sm">กลับหน้าหลัก</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong>เพิ่มอุปกรณ์ใหม่</strong></div>
        <div class="card-body">
            <form action="process_manage.php?action=add" method="POST" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">ชื่อจุดติดตั้ง</label>
                    <input type="text" name="name" class="form-control" placeholder="เช่น ลำโพงหน้าเสาธง" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">IP Address / Domain</label>
                    <input type="text" name="ip" class="form-control" placeholder="เช่น theoneiot.i234.me หรือ 192.168.1.50" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Port</label>
                    <input type="number" name="port" class="form-control" value="80" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">เพิ่ม Node</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ชื่อจุดติดตั้ง</th>
                        <th>ที่อยู่ (IP/Domain:Port)</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nodes)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีข้อมูล Node ในระบบ</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($nodes as $node): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($node['name']) ?></strong></td>
                        <td>
                            <code><?= htmlspecialchars($node['ip_address']) ?></code>
                            <span class="text-muted">:</span>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($node['port'] ?? '80') ?></span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning" 
                                    onclick="editNode(<?= $node['id'] ?>, '<?= addslashes($node['name']) ?>', '<?= $node['ip_address'] ?>', '<?= $node['port'] ?? 80 ?>')">
                                <i class="fas fa-edit"></i> แก้ไข
                            </button>
                            <a href="process_manage.php?action=delete&id=<?= $node['id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('คุณต้องการลบ Node นี้ใช่หรือไม่?')">
                                <i class="fas fa-trash"></i> ลบ
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="process_manage.php?action=edit" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">แก้ไขข้อมูล Node</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label">ชื่อจุดติดตั้ง</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">IP Address / Domain</label>
                        <input type="text" name="ip" id="edit_ip" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" id="edit_port" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * ฟังก์ชันสำหรับเปิด Modal และใส่ข้อมูลเดิมลงในฟอร์มแก้ไข
 * @param {number} id - ID ของ Node
 * @param {string} name - ชื่อจุดติดตั้ง
 * @param {string} ip - IP Address หรือ Domain
 * @param {number} port - เลข Port
 */
function editNode(id, name, ip, port) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_ip').value = ip;
    document.getElementById('edit_port').value = port;
    
    // แสดง Modal
    var myModal = new bootstrap.Modal(document.getElementById('editModal'));
    myModal.show();
}
</script>
</body>
</html>