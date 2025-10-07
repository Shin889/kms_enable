<?php require_once __DIR__ . '/../src/init.php'; require_role(['clerk','admin']); csrf_check();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $uid = $_POST['recipient_uid'];
  $subject = $_POST['subject'];
  $body = $_POST['body'];
  $type = $_POST['type'] ?: 'general';
  notify_user($uid, $type, $subject, nl2br(htmlspecialchars($body)), $_SESSION['uid']);
  redirect('messages.php');
}

$log = db()->query("
  SELECT m.*, ru.email AS recipient_email, su.email AS sender_email
  FROM messages_log m
  LEFT JOIN users ru ON ru.uid=m.recipient_uid
  LEFT JOIN users su ON su.uid=m.sender_uid
  ORDER BY m.sent_timestamp DESC LIMIT 100
")->fetchAll();
?>
<!doctype html><html><body>
<link rel="stylesheet" type="text/css" href="assets/utils/messages.css">
<div class="container">
<h3>Messages</h3>
<form method="post">
  <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
  <label>Recipient UID <input name="recipient_uid" required></label>    
  <label>Type <input name="type" value="general"></label>
  <label>Subject <input name="subject" required></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button>Send</button>
</form>
</div>
<div class="container">
<h4>Recent Log</h4>
<table border="1" cellpadding="6">
<tr><th>Time</th><th>Recipient</th><th>Sender</th><th>Type</th><th>Via</th><th>Content</th></tr>
<?php foreach($log as $m): ?>
<tr>
  <td><?= htmlspecialchars($m['sent_timestamp']) ?></td>
  <td><?= htmlspecialchars($m['recipient_email']) ?></td>
  <td><?= htmlspecialchars($m['sender_email']) ?></td>
  <td><?= htmlspecialchars($m['message_type']) ?></td>
  <td><?= htmlspecialchars($m['sent_via']) ?></td>
  <td><?= htmlspecialchars($m['message_content']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</body></html>
