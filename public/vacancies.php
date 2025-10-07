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
    <title>Job Vacancies</title>
    <link rel="stylesheet" type="text/css" href="assets/utils/vacancies.css">
</head>

<body>
    <div class="container">
        <h3>Job Vacancies</h3>

        <div class="controls">
            <div class="search-sort-row">
                <div class="search-box">
                    <div class="search-icon">🔍</div>
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

        <div class="vacancies-container" id="vacanciesContainer">
            <?php if (isset($vacancies) && !empty($vacancies)): ?>
                <?php foreach ($vacancies as $v): ?>
                    <div class="vacancy-card" data-skills="<?= strtolower(htmlspecialchars($v['skills_required'])) ?>"
                        data-title="<?= strtolower(htmlspecialchars($v['job_title'])) ?>" data-date="<?= $v['date_posted'] ?>"
                        data-deadline="<?= $v['application_deadline'] ?>">

                        <div class="vacancy-header">
                            <h4>
                                <?= htmlspecialchars($v['job_title']) ?>
                                <span style="font-size: 0.9em; color: #3B82F6; margin-left: 5px;">
                                    (Slots: <?= htmlspecialchars($v['candidate_slot'] ?? 'N/A') ?>)
                                </span>
                            </h4>
                            <div class="deadline-badge" data-deadline="<?= $v['application_deadline'] ?>">
                                Due <?= date('M j', strtotime($v['application_deadline'])) ?>
                            </div>
                        </div>


                        <div class="vacancy-meta">
                            <div class="meta-item">
                                <span class="meta-icon">📅</span>
                                Posted: <?= date('M j, Y', strtotime($v['date_posted'])) ?>
                            </div>
                        </div>

                        <div class="vacancy-description">
                            <?= nl2br(htmlspecialchars($v['job_description'])) ?>
                        </div>

                        <div class="skills-container">
                            <div class="skills-list">
                                <?php
                                $skillsData = json_decode($v['skills_required'], true);
                                $skills = [];

                                if (is_array($skillsData)) {
                                    if (isset($skillsData['skills'])) {
                                        $skills = $skillsData['skills'];
                                    } else {
                                        $skills = $skillsData;
                                    }
                                }

                                foreach ($skills as $skill): ?>
                                    <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                <?php endforeach; ?>




                            </div>
                        </div>

                        <?php if ($u && $u['role'] === 'applicant'): ?>
                            <form action="apply.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                                <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">

                                <div class="form-group">
                                    <label class="form-label">📄 Resume (Required)</label>
                                    <input type="file" name="resume" class="form-input" required accept=".pdf,.doc,.docx">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">📝 Cover Letter</label>
                                    <input type="file" name="cover_letter" class="form-input" accept=".pdf,.doc,.docx">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">📎 Additional Requirements</label>
                                    <input type="file" name="requirements" class="form-input" accept=".pdf,.doc,.docx">
                                </div>

                                <button type="submit" class="apply-button">Apply Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">📋</div>
                    <div>No job vacancies available at the moment.</div>
                    <div style="margin-top: 8px; font-size: 14px;">Please check back later for new opportunities.</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="no-results" id="noResults" style="display: none;">
            <div class="no-results-icon">🔍</div>
            <div>No job vacancies found matching your criteria.</div>
            <div style="margin-top: 8px; font-size: 14px;">Try adjusting your search or filters.</div>
        </div>
    </div>

    <script src="assets/scripts/vacancies.js"></script>
</body>

</html>