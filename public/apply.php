<?php 
require_once __DIR__ . '/../src/init.php'; 
require_role(['applicant']); 
csrf_check(); 
ensure_upload_dirs();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    redirect('vacancies.php'); 
}

$uid = $_SESSION['uid'];
$vacancy_id = (int)($_POST['vacancy_id'] ?? 0);

$resume = isset($_FILES['resume']) ? accept_upload($_FILES['resume'], 'resumes') : null;
$cover  = isset($_FILES['cover_letter']) ? accept_upload($_FILES['cover_letter'], 'cover_letters') : null;
$req    = isset($_FILES['requirements']) ? accept_upload($_FILES['requirements'], 'requirements') : null;

// store requirements_docs as a JSON array (so applications_review can decode it reliably)
$req_json = null;
if ($req !== null) {
    $req_json = json_encode([$req]); // wrap single path into array
}

db()->prepare("
    INSERT INTO applications (vacancy_id, applicant_uid, resume_path, cover_letter_path, requirements_docs) 
    VALUES (?,?,?,?,?)
")->execute([$vacancy_id, $uid, $resume, $cover, $req_json]);

// Notify applicant
notify_user(
    $uid, 
    'application_submission', 
    'Application Received',
    '<p>Your application has been submitted. We will update you soon.</p>', 
    $uid
);

redirect('my_applications.php');  
