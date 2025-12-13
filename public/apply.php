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

// Check if vacancy exists and is still open
$currentDateTime = date('Y-m-d H:i:s');
$stmt = db()->prepare("SELECT * FROM job_vacancies 
                      WHERE vacancy_id = ? 
                      AND status = 'open' 
                      AND application_deadline > ?");
$stmt->execute([$vacancy_id, $currentDateTime]);
$vacancy = $stmt->fetch();

if (!$vacancy) {
    $_SESSION['error'] = 'This vacancy is no longer available or has expired';
    redirect('vacancies.php');
}

// Check if user has already applied for this vacancy
$stmt = db()->prepare("SELECT * FROM applications 
                      WHERE vacancy_id = ? AND applicant_uid = ?");
$stmt->execute([$vacancy_id, $uid]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'You have already applied for this position';
    redirect('vacancies.php');
}

// Process all required and optional documents
$documents = [
    'application_letter' => ['required' => true, 'folder' => 'application_letters'],
    'letter_of_intent' => ['required' => true, 'folder' => 'letters_of_intent'],
    'transcript' => ['required' => false, 'folder' => 'transcripts'],
    'service_record' => ['required' => false, 'folder' => 'service_records'],
    'training_certificates' => ['required' => false, 'folder' => 'training_certificates'],
    'performance_rating' => ['required' => false, 'folder' => 'performance_ratings'],
    'appointment' => ['required' => false, 'folder' => 'appointments'],
    'other_documents' => ['required' => false, 'folder' => 'other_documents']
];

$uploaded_files = [];
$requirements_data = [];

// Validate required files
foreach ($documents as $field => $config) {
    if ($config['required'] && (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK)) {
        $_SESSION['error'] = "Please upload the required $field";
        redirect('vacancies.php');
    }
}

// Process each file upload
foreach ($documents as $field => $config) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = accept_upload($_FILES[$field], $config['folder']);
        if ($uploaded_file) {
            $uploaded_files[$field] = $uploaded_file;
            
            // Add to requirements data for JSON storage
            $requirements_data[$field] = [
                'original_name' => $_FILES[$field]['name'],
                'uploaded_path' => $uploaded_file,
                'uploaded_at' => date('Y-m-d H:i:s'),
                'file_size' => $_FILES[$field]['size']
            ];
        }
    }
}

// Check if required documents were uploaded
if (!isset($uploaded_files['application_letter']) || !isset($uploaded_files['letter_of_intent'])) {
    $_SESSION['error'] = 'Application Letter and Letter of Intent are required';
    redirect('vacancies.php');
}

// Prepare the SQL query based on your table structure
// First, let's check what columns exist in the applications table
$stmt = db()->prepare("SHOW COLUMNS FROM applications");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the insert query dynamically based on available columns
$available_document_fields = [
    'application_letter' => 'application_letter_path',
    'letter_of_intent' => 'letter_of_intent_path',
    'transcript' => 'transcript_path',
    'service_record' => 'service_record_path',
    'training_certificates' => 'training_certificates_path',
    'performance_rating' => 'performance_rating_path',
    'appointment' => 'appointment_path',
    'other_documents' => 'other_documents_path'
];

// Start with basic columns
$insert_columns = ['vacancy_id', 'applicant_uid', 'date_applied', 'status'];
$insert_values = [$vacancy_id, $uid, date('Y-m-d H:i:s'), 'submitted'];
$placeholders = ['?', '?', '?', '?'];

// Add document columns if they exist in the table
foreach ($available_document_fields as $form_field => $db_column) {
    if (in_array($db_column, $columns)) {
        $insert_columns[] = $db_column;
        $insert_values[] = $uploaded_files[$form_field] ?? null;
        $placeholders[] = '?';
    }
}

// Add requirements_docs JSON
if (in_array('requirements_docs', $columns)) {
    $insert_columns[] = 'requirements_docs';
    $insert_values[] = !empty($requirements_data) ? json_encode($requirements_data) : null;
    $placeholders[] = '?';
}

// For backward compatibility with original columns
if (in_array('resume_path', $columns)) {
    $insert_columns[] = 'resume_path';
    $insert_values[] = $uploaded_files['application_letter'] ?? null;
    $placeholders[] = '?';
}

if (in_array('cover_letter_path', $columns)) {
    $insert_columns[] = 'cover_letter_path';
    $insert_values[] = $uploaded_files['letter_of_intent'] ?? null;
    $placeholders[] = '?';
}

// Insert the application
$sql = "INSERT INTO applications (" . implode(', ', $insert_columns) . ") 
        VALUES (" . implode(', ', $placeholders) . ")";

try {
    db()->prepare($sql)->execute($insert_values);
    
    // Send notification
    notify_user(
        $uid, 
        'application_submission', 
        'Application Received',
        '<p>Your application has been submitted successfully. We will review your documents and update you soon.</p>', 
        $uid
    );
    
    $_SESSION['success'] = 'Application submitted successfully!';
    redirect('my_applications.php');
    
} catch (Exception $e) {
    // Clean up uploaded files if insertion fails
    foreach ($uploaded_files as $file) {
        if ($file && file_exists(__DIR__ . '/../uploads/' . $file)) {
            unlink(__DIR__ . '/../uploads/' . $file);
        }
    }
    
    $_SESSION['error'] = 'Failed to submit application: ' . $e->getMessage();
    redirect('vacancies.php');
}