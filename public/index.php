<?php
require_once __DIR__ . '/../src/init.php';
$u = current_user();

if ($u) {
    header("Location: dashboard.php");
    exit();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/index.css" />
</head>

<body>
    <div class="page-container">
        <section class="hero">
            <h1>KMS Enable Recruitment</h1>
            <p>Empowering organizations with seamless recruitment.
                Manage job vacancies, track applicants, and streamline employee integration.</p>
        </section>
        
        <div class="content-wrapper">
            <div class="nav-image">
                <img src="assets/images/catsu.png" alt="University" />
            </div>
            
            <div class="nav-container">
                <nav class="main-nav nav-box">
                    <a href="vacancies.php">Job Vacancy</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                </nav>
            </div>
        </div>
    </div>
</body>

</html>
