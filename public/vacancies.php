<?php require_once __DIR__ . '/../src/init.php';

$st = db()->query("SELECT * FROM job_vacancies WHERE status='open' ORDER BY date_posted DESC");
$vacancies = $st->fetchAll();
$u = current_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Vacancies | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/vacancies.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>

<body>
    <div class="container">
        <div class="header">
            <h3>Job Vacancies</h3>
            <p>Browse available positions and apply to join our team</p>
        </div>

        <div class="controls">
            <div class="search-sort-row">
                <div class="search-box">
                    <div class="search-icon"><i class="fas fa-search"></i></div>
                    <input type="text" class="search-input" placeholder="Search job titles, skills, or descriptions..."
                        id="searchInput">
                </div>
                <select class="sort-select" id="sortSelect">
                    <option value="date_desc">Newest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="title_asc">Title A-Z</option>
                    <option value="title_desc">Title Z-A</option>
                    <option value="deadline_asc">Deadline Soon</option>
                </select>
                <select class="view-toggle" id="viewToggle">
                    <option value="all">All Jobs</option>
                    <option value="recent">Recent (7 days)</option>
                    <option value="urgent">Urgent Deadline</option>
                </select>
            </div>
        </div>

        <div class="results-info" id="resultsInfo">
            Showing <span id="resultCount"><?= isset($vacancies) ? count($vacancies) : 0 ?></span> job vacancies
        </div>

        <div class="vacancies-table-container">
            <table class="vacancies-table" id="vacanciesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-briefcase"></i> Job Title</th>
                        <th><i class="fas fa-calendar"></i> Date Posted</th>
                        <th><i class="fas fa-clock"></i> Deadline</th>
                      <!--   <th><i class="fas fa-tools"></i> Skills Required</th> -->
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="vacanciesBody">
                    <?php if (isset($vacancies) && !empty($vacancies)): ?>
                        <?php foreach ($vacancies as $v): 
                            $deadlineClass = '';
                            $deadline = strtotime($v['application_deadline']);
                            $now = time();
                            $daysLeft = floor(($deadline - $now) / (60 * 60 * 24));
                            
                            if ($daysLeft <= 3) {
                                $deadlineClass = 'urgent';
                            } else {
                                $deadlineClass = 'normal';
                            }
                            
                            $skillsData = json_decode($v['skills_required'], true);
                            $skills = [];
                            
                            if (is_array($skillsData)) {
                                if (isset($skillsData['skills'])) {
                                    $skills = $skillsData['skills'];
                                } else {
                                    $skills = $skillsData;
                                }
                            }
                        ?>
                            <tr class="vacancy-row" 
                                data-skills="<?= strtolower(htmlspecialchars($v['skills_required'])) ?>"
                                data-title="<?= strtolower(htmlspecialchars($v['job_title'])) ?>" 
                                data-date="<?= $v['date_posted'] ?>"
                                data-deadline="<?= $v['application_deadline'] ?>"
                                data-id="<?= $v['vacancy_id'] ?>">
                                
                                <td class="job-title-cell">
                                    <?= htmlspecialchars($v['job_title']) ?>
                                    <span class="slots-badge"><?= htmlspecialchars($v['candidate_slot'] ?? 'N/A') ?> slots</span>
                                    <div class="meta-info">
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="fas fa-calendar"></i></span>
                                            Posted: <?= date('M j, Y', strtotime($v['date_posted'])) ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <?= date('M j, Y', strtotime($v['date_posted'])) ?>
                                </td>
                                
                                <td class="deadline-cell">
                                    <span class="deadline-badge <?= $deadlineClass ?>" data-deadline="<?= $v['application_deadline'] ?>">
                                        <i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($v['application_deadline'])) ?>
                                    </span>
                                </td>
                                
                                <!-- <td class="skills-cell">
                                    <div class="skills-list">
                                        <?php foreach (array_slice($skills, 0, 3) as $skill): ?>
                                            <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($skills) > 3): ?>
                                            <span class="skill-tag">+<?= count($skills) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </td> -->
                                
                                <td class="action-cell">
                                    <button class="toggle-details-btn" data-id="<?= $v['vacancy_id'] ?>">
                                        <i class="fas fa-chevron-down"></i> View Details
                                    </button>
                                </td>
                            </tr>
                            
                            <tr class="expand-details" id="details-<?= $v['vacancy_id'] ?>">
                                <td colspan="5">
                                    <div class="details-content">
                                        <div class="details-grid">
                                            <div class="description-section">
                                                <h5>Job Description</h5>
                                                <p><?= nl2br(htmlspecialchars($v['job_description'])) ?></p>
                                            </div>
                                            
                                            <div class="details-section">
                                                <h5>Required Skills</h5>
                                                <div class="skills-list">
                                                    <?php foreach ($skills as $skill): ?>
                                                        <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="details-section">
                                                <h5>Additional Information</h5>
                                                <p><strong>Slots Available:</strong> <?= htmlspecialchars($v['candidate_slot'] ?? 'N/A') ?></p>
                                                <p><strong>Application Deadline:</strong> <?= date('F j, Y', strtotime($v['application_deadline'])) ?></p>
                                                <p><strong>Days Left:</strong> <?= $daysLeft ?> days</p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($u && $u['role'] === 'applicant'): ?>
                                            <div class="apply-form">
                                                <form action="apply.php" method="post" enctype="multipart/form-data">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                                    <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">
                                                    
                                                    <div class="details-grid">
                                                        <div class="form-group">
                                                            <label class="form-label"><i class="fas fa-file-pdf"></i> Resume (Required)</label>
                                                            <input type="file" name="resume" class="form-input" required accept=".pdf,.doc,.docx">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><i class="fas fa-file-alt"></i> Cover Letter</label>
                                                            <input type="file" name="cover_letter" class="form-input" accept=".pdf,.doc,.docx">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label"><i class="fas fa-paperclip"></i> Additional Requirements</label>
                                                        <input type="file" name="requirements" class="form-input" accept=".pdf,.doc,.docx">
                                                    </div>
                                                    
                                                    <button type="submit" class="apply-button">
                                                        <i class="fas fa-paper-plane"></i> Apply Now
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-results-row">
                            <td colspan="5">
                                <div class="no-results">
                                    <div class="no-results-icon"><i class="fas fa-briefcase"></i></div>
                                    <h4>No Job Vacancies Available</h4>
                                    <p>We don't have any open positions at the moment.</p>
                                    <p>Please check back later for new opportunities.</p>
                                    <a href="index.php" class="back-link">
                                        <i class="fas fa-home"></i> Back to Home
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="no-results" id="noResults" style="display: none;">
            <div class="no-results-icon"><i class="fas fa-search"></i></div>
            <h4>No Results Found</h4>
            <p>We couldn't find any job vacancies matching your criteria.</p>
            <p>Try adjusting your search or filters.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const viewToggle = document.getElementById('viewToggle');
            const vacanciesBody = document.getElementById('vacanciesBody');
            const resultCount = document.getElementById('resultCount');
            const resultsInfo = document.getElementById('resultsInfo');
            const noResults = document.getElementById('noResults');
            
            const originalRows = Array.from(vacanciesBody.querySelectorAll('.vacancy-row'));
            
            // Toggle details functionality
            document.querySelectorAll('.toggle-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const detailsRow = document.getElementById('details-' + id);
                    const icon = this.querySelector('i');
                    
                    if (detailsRow.classList.contains('expanded')) {
                        detailsRow.classList.remove('expanded');
                        icon.className = 'fas fa-chevron-down';
                        this.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                    } else {
                        // Close any other open details
                        document.querySelectorAll('.expand-details.expanded').forEach(row => {
                            row.classList.remove('expanded');
                            const btnId = row.id.replace('details-', '');
                            const btn = document.querySelector(`[data-id="${btnId}"]`);
                            if (btn) {
                                btn.querySelector('i').className = 'fas fa-chevron-down';
                                btn.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                            }
                        });
                        
                        detailsRow.classList.add('expanded');
                        icon.className = 'fas fa-chevron-up';
                        this.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
                    }
                });
            });
            
            function updateDeadlineBadges() {
                const badges = document.querySelectorAll('.deadline-badge');
                const now = new Date();
                
                badges.forEach(badge => {
                    const deadlineStr = badge.getAttribute('data-deadline');
                    if (deadlineStr) {
                        const deadline = new Date(deadlineStr);
                        const daysLeft = Math.ceil((deadline - now) / (1000 * 60 * 60 * 24));
                        
                        badge.classList.remove('urgent', 'normal');
                        if (daysLeft <= 3) {
                            badge.classList.add('urgent');
                        } else {
                            badge.classList.add('normal');
                        }
                    }
                });
            }
            
            function filterAndSortVacancies() {
                const searchTerm = searchInput.value.toLowerCase();
                const sortValue = sortSelect.value;
                const viewValue = viewToggle.value;
                
                let filtered = [...originalRows];
                
                // Filter by search
                if (searchTerm) {
                    filtered = filtered.filter(row => {
                        const title = row.getAttribute('data-title') || '';
                        const skills = row.getAttribute('data-skills') || '';
                        return title.includes(searchTerm) || skills.includes(searchTerm);
                    });
                }
                
                // Filter by view
                const now = new Date();
                if (viewValue === 'recent') {
                    const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    filtered = filtered.filter(row => {
                        const datePosted = new Date(row.getAttribute('data-date'));
                        return datePosted >= sevenDaysAgo;
                    });
                } else if (viewValue === 'urgent') {
                    filtered = filtered.filter(row => {
                        const deadline = new Date(row.getAttribute('data-deadline'));
                        const daysLeft = Math.ceil((deadline - now) / (1000 * 60 * 60 * 24));
                        return daysLeft <= 3;
                    });
                }
                
                // Sort
                filtered.sort((a, b) => {
                    switch (sortValue) {
                        case 'date_asc':
                            return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
                        case 'date_desc':
                            return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
                        case 'title_asc':
                            return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
                        case 'title_desc':
                            return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
                        case 'deadline_asc':
                            return new Date(a.getAttribute('data-deadline')) - new Date(b.getAttribute('data-deadline'));
                        default:
                            return 0;
                    }
                });
                
                // Clear and rebuild table
                vacanciesBody.innerHTML = '';
                
                if (filtered.length > 0) {
                    filtered.forEach(row => {
                        const id = row.getAttribute('data-id');
                        const detailsRow = document.getElementById('details-' + id);
                        vacanciesBody.appendChild(row);
                        if (detailsRow) {
                            vacanciesBody.appendChild(detailsRow);
                        }
                    });
                    noResults.style.display = 'none';
                    document.querySelector('.vacancies-table-container').style.display = 'block';
                } else {
                    document.querySelector('.vacancies-table-container').style.display = 'none';
                    noResults.style.display = 'block';
                }
                
                // Update count
                resultCount.textContent = filtered.length;
                
                // Update deadline badges
                updateDeadlineBadges();
                
                // Reattach event listeners for toggle buttons
                document.querySelectorAll('.toggle-details-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const detailsRow = document.getElementById('details-' + id);
                        const icon = this.querySelector('i');
                        
                        if (detailsRow.classList.contains('expanded')) {
                            detailsRow.classList.remove('expanded');
                            icon.className = 'fas fa-chevron-down';
                            this.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                        } else {
                            // Close any other open details
                            document.querySelectorAll('.expand-details.expanded').forEach(row => {
                                row.classList.remove('expanded');
                                const btnId = row.id.replace('details-', '');
                                const btn = document.querySelector(`[data-id="${btnId}"]`);
                                if (btn) {
                                    btn.querySelector('i').className = 'fas fa-chevron-down';
                                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
                                }
                            });
                            
                            detailsRow.classList.add('expanded');
                            icon.className = 'fas fa-chevron-up';
                            this.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
                        }
                    });
                });
            }
            
            // Event listeners
            searchInput.addEventListener('input', filterAndSortVacancies);
            sortSelect.addEventListener('change', filterAndSortVacancies);
            viewToggle.addEventListener('change', filterAndSortVacancies);
            
            // Initial update
            updateDeadlineBadges();
        });
    </script>
</body>

</html>