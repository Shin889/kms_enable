<?php
require_once __DIR__ . '/../src/init.php';
$u = current_user();

// redirect if not logged in
if (!$u) {
    header("Location: index.php");
    exit;
}

// determine which page to load
$allowed_pages = [
    'vacancies',
    'applications_review',
    'applications_admin',
    'employee_tracking',
    'messages',
    'vacancy_manage',
    'my_applications',
    'profile'
];

$page = $_GET['page'] ?? 'vacancies';
if (!in_array($page, $allowed_pages)) {
    $page = 'vacancies';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KMS Dashboard</title>
  <link rel="stylesheet" type="text/css" href="assets/utils/dashboard.css">
</head>
<body>
  <div class="layout">

    <!-- Sidebar -->
    <nav class="sidebar">
      <h2 class="logo">KMS Enable</h2>
      <a href="dashboard.php?page=vacancies">Job Vacancies</a>
      <?php if ($u['role'] === 'clerk'): ?>
        <a href="dashboard.php?page=applications_review">Applications (Clerk)</a>
        <a href="dashboard.php?page=employee_tracking">Employee Tracking</a>
        <a href="dashboard.php?page=messages">Messages</a>
      
      <?php elseif ($u['role'] === 'admin'): ?>
        <a href="dashboard.php?page=applications_admin">Applications (Admin)</a>
        <a href="dashboard.php?page=employee_tracking">Employee Tracking</a>
        <a href="dashboard.php?page=messages">Messages</a>
        <a href="dashboard.php?page=vacancy_manage">Manage Vacancies</a>
      
      <?php else: ?>
        <a href="dashboard.php?page=my_applications">My Applications</a>
        <a href="dashboard.php?page=profile">My Profile</a>
      <?php endif; ?>
      <a href="logout.php">Logout</a>
    </nav>
    <!-- Main content -->
    <main class="content">

      <div class="page-container">
        <?php include __DIR__ . "/{$page}.php"; ?>
      </div>
    </main>

  </div>
      <script src="assets/scripts/sidebar.js"></script>
</body>
</html>
