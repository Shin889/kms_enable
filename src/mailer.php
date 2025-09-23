<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailer(): PHPMailer {
  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host = SMTP_HOST;
  $m->SMTPAuth = true;
  $m->Username = SMTP_USER;
  $m->Password = SMTP_PASS;
  $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $m->Port = SMTP_PORT;
  [$fromName, $fromEmail] = parse_from(SMTP_FROM);
  $m->setFrom($fromEmail, $fromName);
  return $m;
}

function parse_from($from) {
  if (preg_match('/^(.*?)\s*<(.+?)>$/', $from, $m)) return [$m[1], $m[2]];
  return ['KMS RecruitHub', $from];
}

function send_mail_and_log(string $recipient_uid, string $toEmail, string $subject, string $html, string $type, ?string $sender_uid = null) {
  // log first (system)
  db()->prepare("INSERT INTO messages_log (recipient_uid, sender_uid, message_type, message_content, sent_via) VALUES (?,?,?,?, 'system')")
     ->execute([$recipient_uid, $sender_uid, $type, $subject]);

  try {
    $m = mailer();
    $m->addAddress($toEmail);
    $m->isHTML(true);
    $m->Subject = $subject;
    $m->Body = $html;
    $m->AltBody = strip_tags($html);
    $m->send();

    // log as gmail too
    db()->prepare("INSERT INTO messages_log (recipient_uid, sender_uid, message_type, message_content, sent_via) VALUES (?,?,?,?, 'gmail')")
       ->execute([$recipient_uid, $sender_uid, $type, $subject]);
  } catch (Exception $e) {
    error_log('Mailer error: ' . $e->getMessage());
    file_put_contents(dirname(__DIR__) . '/storage/logs/mail.log', date('c') . ' ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
  }
}
