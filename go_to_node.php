<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) exit;

$ip = $_GET['ip'];
$port = $_GET['port'];
$key = "*sS5383160"; // รหัสลับ
?>
<html>
<body onload="document.forms[0].submit()">
    <form action="http://<?php echo $ip; ?>:<?php echo $port; ?>/" method="POST">
        <input type="hidden" name="key" value="<?php echo $key; ?>">
        <p>Redirecting to Node...</p>
    </form>
</body>
</html>