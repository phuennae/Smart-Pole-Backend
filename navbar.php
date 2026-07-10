<?php
// ดึงค่าสิทธิ์ผู้ใช้จาก Session มาเก็บในตัวแปร $role 
// หากไม่มีค่า (เช่น session หลุด) ให้ตั้งเป็น guest เพื่อป้องกัน Error
$role = $_SESSION['role'] ?? 'guest';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-broadcast-tower text-info"></i> Smart Pole
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'cctv.php' ? 'active' : '' ?>" href="cctv.php">
                        <i class="fas fa-video me-1"></i> CCTV
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-bullhorn me-1"></i> เสียงตามสาย
                    </a>
                </li>
				                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="live.php">
                        <i class="fas fa-bullhorn me-1"></i> Live Broadcast
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'smart_pole.php' ? 'active' : '' ?>" href="smart_pole.php">
                        <i class="fas fa-bolt me-1"></i> ระบบไฟฟ้า
                    </a>
                </li>
            </ul>
            
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> 
                        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> 
                        (<?= strtoupper(htmlspecialchars($role)) ?>)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <?php if ($role == 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="manage_nodes.php">
                                    <i class="fas fa-server me-2"></i> จัดการอุปกรณ์ (Nodes)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="manage_users.php">
                                    <i class="fas fa-users me-2"></i> จัดการสมาชิก
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>