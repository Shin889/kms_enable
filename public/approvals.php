<?php
require_once __DIR__ . '/../src/init.php';
$me = current_user();
if (!$me || $me['role'] !== 'admin') {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $uid = $_POST['uid'] ?? '';
  if ($action === 'approve') {
    db()->prepare("UPDATE users SET account_status = 'active' WHERE uid = ?")->execute([$uid]);
    notify_user($uid, 'account_approved', 'Account Approved', '<p>Your account has been approved by admin.</p>');
  } elseif ($action === 'reject') {
    db()->prepare("UPDATE users SET account_status = 'rejected' WHERE uid = ?")->execute([$uid]);
    notify_user($uid, 'account_rejected', 'Account Rejected', '<p>Your account was rejected. Contact admin for help.</p>');
  }
  redirect('dashboard.php?page=approvals');
}

$st = db()->prepare("SELECT * FROM users WHERE account_status = 'pending' AND role = 'clerk' ORDER BY uid ASC");
$st->execute();
$pending = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Pending Clerk Accounts</h2>

<?php if (empty($pending)): ?>
  <p>No pending clerk accounts.</p>
<?php else: ?>
  <table border="1" cellpadding="6" cellspacing="0">
    <tr>
      <th>UID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($pending as $clerk): ?>
      <tr>
        <td><?= htmlspecialchars($clerk['uid']) ?></td>
        <td><?= htmlspecialchars($clerk['firstName'] . ' ' . $clerk['lastName']) ?></td>
        <td><?= htmlspecialchars($clerk['email']) ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="uid" value="<?= $clerk['uid'] ?>">
            <button type="submit" name="action" value="approve">Approve</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="uid" value="<?= $clerk['uid'] ?>">
            <button type="submit" name="action" value="reject">Reject</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
