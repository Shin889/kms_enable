<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['president','admin']); 
csrf_check();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    // Validate recipient is selected
    if (empty($_POST['recipient_uid'])) {
        $_SESSION['flash'] = 'Error: Please select a recipient';
        redirect('messages.php');
        exit;
    }
    
    $uid = $_POST['recipient_uid'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $type = $_POST['type'] ?: 'general';
    
    notify_user($uid, $type, $subject, nl2br(htmlspecialchars($body)), $_SESSION['uid']);
    
    $_SESSION['flash'] = 'Message sent successfully!';
    redirect('messages.php');
}

// Get users for recipient dropdown
$users = db()->query("
    SELECT uid, email, firstName, lastName 
    FROM users 
    WHERE role IN ('applicant', 'president')
    ORDER BY firstName, lastName
")->fetchAll();

$log = db()->query("
    SELECT m.*, 
           ru.email AS recipient_email, 
           ru.firstName AS recipient_first, 
           ru.lastName AS recipient_last,
           su.email AS sender_email,
           su.firstName AS sender_first,
           su.lastName AS sender_last
    FROM messages_log m
    LEFT JOIN users ru ON ru.uid = m.recipient_uid
    LEFT JOIN users su ON su.uid = m.sender_uid
    ORDER BY m.sent_timestamp DESC 
    LIMIT 100
")->fetchAll();

// Check for flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/messages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- <div class="header">
            <h3>Messages</h3>
            <p class="subtitle">Send notifications and view message history</p>
        </div> -->

        <?php if ($flash): ?>
            <div class="flash-message">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($flash) ?></div>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="message-form-container">
                <h4><i class="fas fa-paper-plane"></i> Send New Message</h4>
                <form method="post" class="message-form">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label>Recipient <span>*</span></label>
                        <!-- <select name="recipient_uid" required style="display: none;">
                            <option value="">Select recipient...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['uid'] ?>">
                                    <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select> -->
                        
                        <div class="user-select" id="userSelect">
                            <?php foreach ($users as $user): 
                                $userName = trim(htmlspecialchars($user['firstName'] . ' ' . $user['lastName']));
                                $userEmail = htmlspecialchars($user['email']);
                            ?>
                                <div class="user-option" data-uid="<?= $user['uid'] ?>">
                                    <div class="user-name"><?= $userName ?></div>
                                    <div class="user-email"><?= $userEmail ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="recipient_uid" id="recipientUid">
                        <div id="selectedUserDisplay" class="selected-user-display" style="display:none; padding:10px; background:#f5f5f5; border-radius:8px; margin-top:8px;">
                            <strong>Selected:</strong> <span id="selectedUserName"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Message Type <span>*</span></label>
                        <div class="type-selectors">
                            <label class="type-option selected">
                                <input type="radio" name="type" value="general" checked>
                                <i class="fas fa-envelope"></i> General
                            </label>
                            <label class="type-option">
                                <input type="radio" name="type" value="registration">
                                <i class="fas fa-user-plus"></i> Registration
                            </label>
                            <label class="type-option">
                                <input type="radio" name="type" value="application_update">
                                <i class="fas fa-file-alt"></i> Application
                            </label>
                            <label class="type-option">
                                <input type="radio" name="type" value="password_reset">
                                <i class="fas fa-key"></i> Password
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject <span>*</span></label>
                        <input type="text" id="subject" name="subject" required 
                               placeholder="Enter message subject...">
                    </div>
                    
                    <div class="form-group">
                        <label for="body">Message Body <span>*</span></label>
                        <textarea id="body" name="body" required 
                                  placeholder="Enter your message here..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <div class="logs-container">
                <div class="logs-header">
                    <h4><i class="fas fa-history"></i> Recent Messages</h4>
                    <div class="log-count"><?= count($log) ?> messages</div>
                </div>
                
                <?php if (!empty($log)): ?>
                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Recipient</th>
                                <th>Sender</th>
                                <th>Type</th>
                                <th>Via</th>
                                <th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($log as $m): 
                                $recipientName = trim(htmlspecialchars(($m['recipient_first'] ?? '') . ' ' . ($m['recipient_last'] ?? '')));
                                $senderName = trim(htmlspecialchars(($m['sender_first'] ?? '') . ' ' . ($m['sender_last'] ?? '')));
                                $timestamp = date('M j, Y g:i A', strtotime($m['sent_timestamp']));
                                $typeClass = 'type-' . str_replace('_', '-', $m['message_type']);
                            ?>
                            <tr>
                                <td class="timestamp"><?= $timestamp ?></td>
                                <td class="recipient-cell">
                                    <div class="recipient-name"><?= $recipientName ?></div>
                                    <div class="recipient-email"><?= htmlspecialchars($m['recipient_email']) ?></div>
                                </td>
                                <td class="sender-cell">
                                    <div class="sender-name"><?= $senderName ?></div>
                                    <div class="sender-email"><?= htmlspecialchars($m['sender_email']) ?></div>
                                </td>
                                <td>
                                    <span class="type-badge <?= $typeClass ?>">
                                        <?= htmlspecialchars($m['message_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="via-badge"><?= htmlspecialchars($m['sent_via']) ?></span>
                                </td>
                                <td>
                                    <div class="message-preview" title="<?= htmlspecialchars($m['message_content']) ?>">
                                        <?= htmlspecialchars($m['message_content']) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-logs">
                    <div class="no-logs-icon"><i class="fas fa-envelope-open"></i></div>
                    <h4>No Messages Sent Yet</h4>
                    <p>No messages have been sent through the system yet.</p>
                    <p>Send your first message using the form on the left.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

       <!--  <div class="action-buttons">
            <a href="dashboard.php" class="action-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="#" class="action-link primary" onclick="document.querySelector('.message-form').submit(); return false;">
                <i class="fas fa-redo"></i> Send Another Message
            </a>
        </div> -->
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // User selection
    const userOptions = document.querySelectorAll('.user-option');
    const recipientUidInput = document.getElementById('recipientUid');
    const selectedUserDisplay = document.getElementById('selectedUserDisplay');
    const selectedUserName = document.getElementById('selectedUserName');
    
    // Add "required" indicator
    const recipientLabel = document.querySelector('label[for="recipientUid"]');
    if (recipientLabel) {
        recipientLabel.innerHTML += ' <span style="color:red">*</span>';
    }
    
    userOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            userOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Set the hidden input value
            const uid = this.getAttribute('data-uid');
            recipientUidInput.value = uid;
            
            // Show selected user display
            const name = this.querySelector('.user-name').textContent;
            const email = this.querySelector('.user-email').textContent;
            selectedUserName.textContent = name + ' (' + email + ')';
            selectedUserDisplay.style.display = 'block';
            
            // Clear any validation error
            recipientUidInput.setCustomValidity('');
        });
    });
    
    // Message type selection
    const typeOptions = document.querySelectorAll('.type-option');
    typeOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        
        option.addEventListener('click', function() {
            // Remove selected class from all options
            typeOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Check the radio button
            radio.checked = true;
        });
    });
    
    // Auto-expand textarea
    const textarea = document.getElementById('body');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    // Filter users
    const userSelectContainer = document.getElementById('userSelect');
    if (userSelectContainer) {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search users...';
        searchInput.style.cssText = 'width:100%; padding:12px 16px; margin-bottom:12px; border-radius:12px; border:1px solid #ddd; font-size:14px;';
        userSelectContainer.parentNode.insertBefore(searchInput, userSelectContainer);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            userOptions.forEach(option => {
                const name = option.querySelector('.user-name').textContent.toLowerCase();
                const email = option.querySelector('.user-email').textContent.toLowerCase();
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
    
    // Form validation before submit
    const form = document.querySelector('.message-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!recipientUidInput.value) {
                e.preventDefault();
                alert('Please select a recipient');
                // Scroll to recipient section
                document.querySelector('.user-select').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            // Validate subject
            const subject = document.getElementById('subject');
            if (!subject || !subject.value.trim()) {
                e.preventDefault();
                alert('Please enter a subject');
                subject.focus();
                return false;
            }
            
            // Validate body
            const body = document.getElementById('body');
            if (!body || !body.value.trim()) {
                e.preventDefault();
                alert('Please enter a message body');
                body.focus();
                return false;
            }
        });
    }
});
</script>
</body>
</html>