<?php
// Quick test: create a dummy record, then delete it and see if audit fires
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// Simulate logged-in session
startSecureSession();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin@krystal';
$_SESSION['role'] = 'admin';
$_SESSION['shift'] = 'all';

// Insert a test record
$db->beginTransaction();
$db->prepare('INSERT INTO attendance_records (shift, attendance_date, created_by) VALUES (?, ?, ?)')->execute(['morning', '2099-01-01', 1]);
$recordId = (int)$db->lastInsertId();
$db->prepare('INSERT INTO attendance_employees (attendance_record_id, employee_name, employee_id, post, status) VALUES (?, ?, ?, ?, ?)')->execute([$recordId, 'Audit Test', 'ATEST001', 'guard', 'present']);
$db->commit();
echo "Created test record ID: $recordId\n";

// Now simulate delete with audit
$stmt = $db->prepare('SELECT * FROM attendance_records WHERE id = ?');
$stmt->execute([$recordId]);
$rec = $stmt->fetch();
echo "Fetched record: date={$rec['attendance_date']} shift={$rec['shift']}\n";

// Perform delete
try {
    $stmt = $db->prepare('DELETE FROM attendance_records WHERE id = ?');
    $stmt->execute([$recordId]);
    echo "Deleted record.\n";
    
    auditLog('ATTENDANCE_DELETE', [
        'target_type'  => 'attendance_record',
        'target_id'    => (int)$recordId,
        'record_date'  => $rec['attendance_date'],
        'record_shift' => $rec['shift'],
    ]);
    echo "auditLog called.\n";
} catch (Exception $e) {
    echo "EXCEPTION in delete: " . $e->getMessage() . "\n";
}

// Check audit log
$pdo = new PDO('mysql:host=localhost;dbname=krystal_attendance;charset=utf8mb4','root','hello brother',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query("SELECT * FROM audit_log WHERE action_type='ATTENDANCE_DELETE' ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo "\nATTENDANCE_DELETE audit rows: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} details={$r['details_json']} created={$r['created_at']}\n";
}
