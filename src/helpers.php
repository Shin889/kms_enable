<?php
function redirect(string $path) {
    // If headers have already been sent, use JavaScript redirect
    if (headers_sent()) {
        echo '<script>';
        echo 'window.location.href = "' . htmlspecialchars($path) . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($path) . '">';
        echo '<p>If you are not redirected, <a href="' . htmlspecialchars($path) . '">click here</a></p>';
        echo '</noscript>';
        exit;
    }
    
    // Clear any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Location: ' . $path);
    exit;
}

function json_response($data, int $status = 200) {
    // Clear buffers for JSON response too
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function uuid(): string {
    $stmt = db()->query("SELECT UUID() as id");
    return $stmt->fetch()['id'];
}