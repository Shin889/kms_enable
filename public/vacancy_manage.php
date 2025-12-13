<?php
require_once __DIR__ . '/../src/init.php';
require_role(['admin']);
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Get skills as plain text
  $skills = trim($_POST['skills_required'] ?? '');

  $message = '';

  // CRUD
  if ($_POST['action'] === 'create') {
    db()->prepare("INSERT INTO job_vacancies (posted_by, job_title, job_description, skills_required, application_deadline, status, candidate_slot)
                   VALUES (?, ?, ?, ?, ?, ?, ?)")
      ->execute([
        $_SESSION['uid'],
        $_POST['job_title'],
        $_POST['job_description'],
        $skills, // plain text
        $_POST['application_deadline'] ?: null,
        $_POST['status'] ?? 'open',
        $_POST['candidate_slot'] ?? 1
      ]);
    $message = "Vacancy created successfully!";
  } elseif ($_POST['action'] === 'update') {
    db()->prepare("UPDATE job_vacancies 
                   SET job_title=?, job_description=?, skills_required=?, application_deadline=?, status=?, candidate_slot=?
                   WHERE vacancy_id=?")
      ->execute([
        $_POST['job_title'],
        $_POST['job_description'],
        $skills, // plain text
        $_POST['application_deadline'] ?: null,
        $_POST['status'] ?? 'open',
        $_POST['candidate_slot'] ?? 1,
        (int) $_POST['vacancy_id']
      ]);
    $message = "Vacancy updated successfully!";
  } elseif ($_POST['action'] === 'delete') {
    db()->prepare("DELETE FROM job_vacancies WHERE vacancy_id=?")->execute([(int) $_POST['vacancy_id']]);
    $message = "Vacancy deleted successfully!";
  }

  $_SESSION['flash'] = $message;
  redirect('vacancy_manage.php');
}

// Fetch vacancies
$list = db()->query("SELECT * FROM job_vacancies ORDER BY date_posted DESC")->fetchAll();

// Get counts for statistics
$stats = [
    'total' => count($list),
    'open' => 0,
    'closed' => 0,
    'expired' => 0
];

foreach ($list as $vacancy) {
    if ($vacancy['status'] === 'open') {
        $stats['open']++;
        
        // Check if deadline has passed
        if (!empty($vacancy['application_deadline'])) {
            $deadline = strtotime($vacancy['application_deadline']);
            if ($deadline < time()) {
                $stats['expired']++;
            }
        }
    } elseif ($vacancy['status'] === 'closed') {
        $stats['closed']++;
    }
}

// Check for flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job Vacancies | Admin</title>
    <link rel="stylesheet" href="assets/utils/vacancy_manage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container"> 
        <div class="header">
            <h3>Manage Job Vacancies</h3>
            <p class="subtitle">Create, update, and delete job vacancy postings</p>
            
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Vacancies</div>
                </div>
                <div class="stat-card open">
                    <div class="stat-number"><?= $stats['open'] ?></div>
                    <div class="stat-label">Open</div>
                </div>
                <div class="stat-card closed">
                    <div class="stat-number"><?= $stats['closed'] ?></div>
                    <div class="stat-label">Closed</div>
                </div>
                <div class="stat-card expired">
                    <div class="stat-number"><?= $stats['expired'] ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($flash) ?></div>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="create-section">
                <h4><i class="fas fa-plus-circle"></i> Create New Vacancy</h4>
                <form method="post" class="create-form">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="job_title">Job Title <span>*</span></label>
                        <input type="text" id="job_title" name="job_title" required 
                               placeholder="e.g., Senior Software Developer">
                    </div>
                    
                    <div class="form-group">
                        <label for="job_description">Job Description <span>*</span></label>
                        <textarea id="job_description" name="job_description" required 
                                  placeholder="Enter detailed job description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="skills_required">Required Skills</label>
                        <textarea id="skills_required" name="skills_required" 
                                  placeholder="Enter required skills (comma-separated)...
e.g., PHP, JavaScript, MySQL, Project Management"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="application_deadline">Application Deadline</label>
                            <input type="date" id="application_deadline" name="application_deadline" 
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="candidate_slot">Candidate Slots</label>
                            <input type="number" id="candidate_slot" name="candidate_slot" 
                                   min="1" value="1" placeholder="Number of openings">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="open" selected>Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Create Vacancy
                    </button>
                </form>
            </div>

            <div class="vacancies-section">
                <h4>
                    <span><i class="fas fa-briefcase"></i> Existing Vacancies</span>
                    <span class="vacancy-count"><?= count($list) ?> vacancies</span>
                </h4>
                
                <?php if (!empty($list)): ?>
                <div class="vacancies-list">
                    <?php foreach ($list as $v): 
                        $skills_display = '';
                        $skills_array = [];

                        if (!empty($v['skills_required'])) {
                            $raw = $v['skills_required'];
                            $decoded = json_decode($raw, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                // If structure is {"skills": ["PHP", "MySQL"]}
                                if (isset($decoded['skills']) && is_array($decoded['skills'])) {
                                    $skills_array = $decoded['skills'];
                                    $skills_display = implode(', ', $skills_array);
                                }
                                // If structure is ["PHP", "MySQL"]
                                elseif (is_array($decoded)) {
                                    $skills_array = $decoded;
                                    $skills_display = implode(', ', $skills_array);
                                }
                            } else {
                                // Plain text, try to parse as comma-separated
                                $skills_array = array_map('trim', explode(',', $raw));
                                $skills_display = $raw;
                            }
                        }
                        
                        $datePosted = date('M j, Y', strtotime($v['date_posted']));
                        $statusClass = 'status-' . $v['status'];
                    ?>
                    <div class="vacancy-card">
                        <div class="vacancy-header">
                            <h5 class="vacancy-title"><?= htmlspecialchars($v['job_title']) ?></h5>
                            <div class="vacancy-meta">
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= ucfirst($v['status']) ?>
                                </span>
                                <span class="date-posted">
                                    <i class="fas fa-calendar"></i> <?= $datePosted ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="vacancy-content">
                            <div class="description-preview">
                                <?= nl2br(htmlspecialchars(substr($v['job_description'], 0, 200))) ?>...
                            </div>
                            
                            <?php if (!empty($skills_array)): ?>
                            <div class="skills-container">
                                <span class="skills-label">Required Skills:</span>
                                <div class="skills-list">
                                    <?php foreach ($skills_array as $skill): 
                                        $skill = trim($skill);
                                        if (!empty($skill)):
                                    ?>
                                        <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($v['application_deadline'])): ?>
                            <div class="date-posted">
                                <i class="fas fa-clock"></i> 
                                Deadline: <?= date('M j, Y', strtotime($v['application_deadline'])) ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($v['candidate_slot'])): ?>
                            <div class="date-posted">
                                <i class="fas fa-users"></i> 
                                Slots: <?= htmlspecialchars($v['candidate_slot']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="vacancy-actions">
                            <button type="button" class="btn btn-small" onclick="toggleEditForm(<?= $v['vacancy_id'] ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this vacancy? This action cannot be undone.');">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-small btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                        
                        <form method="post" class="update-form" id="editForm<?= $v['vacancy_id'] ?>" style="display: none;">
                            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                            <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">
                            <input type="hidden" name="action" value="update">
                            
                            <input type="text" name="job_title" value="<?= htmlspecialchars($v['job_title']) ?>" placeholder="Job Title" required>
                            
                            <textarea name="job_description" placeholder="Job Description" required><?= htmlspecialchars($v['job_description']) ?></textarea>
                            
                            <textarea name="skills_required" placeholder="Skills (comma-separated)"><?= htmlspecialchars($skills_display) ?></textarea>
                            
                            <input type="date" name="application_deadline" value="<?= htmlspecialchars($v['application_deadline']) ?>">
                            
                            <input type="number" name="candidate_slot" value="<?= htmlspecialchars($v['candidate_slot'] ?? 1) ?>" min="1" placeholder="Slots">
                            
                            <select name="status">
                                <option value="open" <?= $v['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="closed" <?= $v['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                            
                            <button type="submit" class="btn btn-small">
                                <i class="fas fa-save"></i> Update Vacancy
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-vacancies">
                    <div class="no-vacancies-icon"><i class="fas fa-briefcase"></i></div>
                    <h4>No Vacancies Yet</h4>
                    <p>No job vacancies have been created yet.</p>
                    <p>Create your first vacancy using the form on the left.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="action-buttons">
            <a href="dashboard.php" class="action-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="vacancies.php" class="action-link">
                <i class="fas fa-eye"></i> View Public Vacancies
            </a>
        </div>
    </div>

    <script>
        function toggleEditForm(vacancyId) {
            const form = document.getElementById('editForm' + vacancyId);
            const isVisible = form.style.display === 'block';
            
            // Hide all other edit forms
            document.querySelectorAll('.update-form').forEach(f => {
                f.style.display = 'none';
            });
            
            // Toggle current form
            form.style.display = isVisible ? 'none' : 'block';
            
            // Scroll to form if opening
            if (!isVisible) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
        
        // Auto-expand textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Trigger initial resize for existing content
            if (textarea.value) {
                textarea.style.height = 'auto';
                textarea.style.height = (textarea.scrollHeight) + 'px';
            }
        });
        
        // Set minimum date for deadline inputs
        const deadlineInputs = document.querySelectorAll('input[type="date"]');
        const today = new Date().toISOString().split('T')[0];
        deadlineInputs.forEach(input => {
            if (!input.value) {
                input.value = today;
            }
            input.min = today;
        });
    </script>
</body>
</html>