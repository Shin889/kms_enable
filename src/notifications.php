<?php
function notify_user(string $recipient_uid, string $type, string $subject, string $html, ?string $sender_uid = null) {
  $st = db()->prepare("SELECT email FROM users WHERE uid = ?");
  $st->execute([$recipient_uid]);
  $email = $st->fetchColumn();
  if ($email) {
    send_mail_and_log($recipient_uid, $email, $subject, $html, $type, $sender_uid);
  }
}
