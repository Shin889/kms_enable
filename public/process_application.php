<?php
require_once __DIR__ . '/../src/init.php';
require_role(['admin']);
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $appId = (int) $_POST['application_id'];
    $adminUid = $_SESSION['uid'];
    
    // Get current application details
    $stmt = db()->prepare("SELECT applicant_uid, status FROM applications WHERE application_id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
    
    if (!$app) {
        redirect('applications_admin.php');
    }
    
    if ($_POST['action'] === 'admin_approve') {
        // Only allow approval if currently approved by president
        if ($app['status'] === 'approved_by_president') {
            db()->prepare("
                UPDATE applications
                SET status = 'approved_by_admin',
                    approval_rejection_reason = NULL,
                    approved_by_uid = ?
                WHERE application_id = ?
            ")->execute([$adminUid, $appId]);

            // Notify applicant
            notify_user(
                $app['applicant_uid'],
                'application_approved_admin',
                'Application Fully Approved',
                '<p>Your application has received final approval from the Administrator.</p>',
                $adminUid
            );
        }
        
    } elseif ($_POST['action'] === 'admin_reject' && isset($_POST['rejection_reason'])) {
        $reason = trim($_POST['rejection_reason']);
        
        // Can reject from any status
        db()->prepare("
            UPDATE applications
            SET status = 'rejected_by_admin',
                approval_rejection_reason = ?,
                approved_by_uid = ?
            WHERE application_id = ?
        ")->execute([$reason, $adminUid, $appId]);

        // Notify applicant
        notify_user(
            $app['applicant_uid'],
            'application_rejected_admin',
            'Application Rejected',
            '<p>Your application has been rejected. Reason: ' . htmlspecialchars($reason) . '</p>',
            $adminUid
        );
    }
    
    // Redirect back to view the application
    redirect('application_view.php?id=' . $appId);
}

// If not POST request, redirect
redirect('applications_admin.php');
?>