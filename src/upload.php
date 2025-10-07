<?php
function ensure_upload_dirs()
{
    foreach (['resumes', 'cover_letters', 'requirements'] as $d) {
        $dir = UPLOAD_DIR . '/' . $d;
        if (!is_dir($dir))
            mkdir($dir, 0775, true);
    }
}

function accept_upload(array $file, string $subdir)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [];
    if ($subdir === 'profile_pictures') {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    } else {
        $allowed = ['pdf', 'doc', 'docx'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return null;
    }

    $baseDir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $targetPath = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return "$subdir/" . $filename;
}
