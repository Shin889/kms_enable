<?php 
require_once __DIR__ . '/../src/init.php';

// Debug: Check if user is logged in
error_log("DEBUG: Starting vacancies.php script");

// Get current date for filtering expired vacancies
$currentDateTime = date('Y-m-d H:i:s');
error_log("DEBUG: Current date: $currentDateTime");

try {
    // Only fetch vacancies that are open AND not expired
    $st = db()->query("SELECT * FROM job_vacancies 
                      WHERE status='open' 
                      AND application_deadline > '$currentDateTime' 
                      ORDER BY date_posted DESC");
    $vacancies = $st->fetchAll();
    error_log("DEBUG: Found " . count($vacancies) . " vacancies");
} catch (Exception $e) {
    error_log("ERROR: Database query failed: " . $e->getMessage());
    $vacancies = [];
}

$u = null;
try {
    $u = current_user();
    error_log("DEBUG: User role: " . ($u ? $u['role'] : 'No user logged in'));
} catch (Exception $e) {
    error_log("ERROR: Getting current user failed: " . $e->getMessage());
}

// Count active vacancies (non-expired)
$activeVacanciesCount = 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Vacancies | KMS Enable Recruitment</title>
    <link rel="stylesheet" href="assets/utils/vacancies.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .expired-row {
            opacity: 0.6;
            background-color: #f8f9fa !important;
        }
        
        .expired-badge {
            background-color: #dc3545 !important;
            color: white !important;
        }
        
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        
        /* Additional styles for document requirements */
        .requirements-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .requirement-item {
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-note {
            font-style: italic;
            color: #666;
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        
        /* Fallback styles if CSS file doesn't load */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        .header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
        }
        
        .controls {
            margin-bottom: 20px;
        }
        
        .search-sort-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            position: relative;
            min-width: 300px;
        }
        
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 10px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .sort-select, .view-toggle {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            font-size: 14px;
            min-width: 150px;
        }
        
        .results-info {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .vacancies-table-container {
            overflow-x: auto;
        }
        
        .vacancies-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .vacancies-table thead {
            background: #2c3e50;
            color: white;
        }
        
        .vacancies-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .vacancies-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .vacancies-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .job-title-cell {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .slots-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .meta-info {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .meta-item {
            display: inline-flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .meta-icon {
            margin-right: 5px;
        }
        
        .deadline-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .deadline-badge.normal {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .deadline-badge.urgent {
            background: #ffebee;
            color: #c62828;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .toggle-details-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .toggle-details-btn:hover {
            background: #2980b9;
        }
        
        .expand-details {
            display: none;
        }
        
        .expand-details.expanded {
            display: table-row;
        }
        
        .details-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .details-section h5, .description-section h5 {
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3498db;
        }
        
        .apply-form {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .apply-button {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .apply-button:hover {
            background: #219653;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-results-icon {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-results h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .days-left-badge {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
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
                    <input type="text" class="search-input" placeholder="Search job titles..."
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
            Showing <span id="resultCount">0</span> job vacancies
        </div>

        <div class="vacancies-table-container">
            <table class="vacancies-table" id="vacanciesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-briefcase"></i> Job Title</th>
                        <th><i class="fas fa-calendar"></i> Date Posted</th>
                        <th><i class="fas fa-clock"></i> Deadline</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="vacanciesBody">
                    <?php 
                    if (empty($vacancies)) {
                        error_log("DEBUG: No vacancies found or query failed");
                    }
                    
                    $currentTime = new DateTime();
                    if (isset($vacancies) && !empty($vacancies)): 
                        foreach ($vacancies as $v): 
                            // Check if vacancy is still active (not expired)
                            $deadlineDate = new DateTime($v['application_deadline']);
                            $isExpired = $deadlineDate < $currentTime;
                            
                            // Skip expired vacancies completely
                            if ($isExpired) {
                                continue;
                            }
                            
                            $activeVacanciesCount++;
                            
                            // Calculate days left
                            $now = time();
                            $deadlineTimestamp = strtotime($v['application_deadline']);
                            $daysLeft = floor(($deadlineTimestamp - $now) / (60 * 60 * 24));
                            
                            // Set deadline badge class
                            $deadlineClass = 'normal';
                            if ($daysLeft <= 3) {
                                $deadlineClass = 'urgent';
                            }
                        ?>
                            <tr class="vacancy-row" 
                                data-title="<?= htmlspecialchars(strtolower($v['job_title'] ?? '')) ?>" 
                                data-date="<?= htmlspecialchars($v['date_posted'] ?? '') ?>"
                                data-deadline="<?= htmlspecialchars($v['application_deadline'] ?? '') ?>"
                                data-id="<?= htmlspecialchars($v['vacancy_id'] ?? '') ?>"
                                data-days-left="<?= htmlspecialchars($daysLeft) ?>">
                                
                                <td class="job-title-cell">
                                    <?= htmlspecialchars($v['job_title'] ?? 'No Title') ?>
                                    <span class="slots-badge"><?= htmlspecialchars($v['candidate_slot'] ?? 'N/A') ?> slots</span>
                                    <div class="meta-info">
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="fas fa-calendar"></i></span>
                                            Posted: <?= date('M j, Y', strtotime($v['date_posted'] ?? 'now')) ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <?= date('M j, Y', strtotime($v['date_posted'] ?? 'now')) ?>
                                </td>
                                
                                <td class="deadline-cell">
                                    <span class="deadline-badge <?= $deadlineClass ?>" data-deadline="<?= htmlspecialchars($v['application_deadline'] ?? '') ?>">
                                        <i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($v['application_deadline'] ?? 'now')) ?>
                                    </span>
                                </td>
                                
                                <td class="action-cell">
                                    <button class="toggle-details-btn" data-id="<?= htmlspecialchars($v['vacancy_id'] ?? '') ?>">
                                        <i class="fas fa-chevron-down"></i> View Details
                                    </button>
                                </td>
                            </tr>
                            
                            <tr class="expand-details" id="details-<?= htmlspecialchars($v['vacancy_id'] ?? '') ?>">
                                <td colspan="5">
                                    <div class="details-content">
                                        <div class="details-grid">
                                            <div class="description-section">
                                                <h5>Job Description</h5>
                                                <p><?= nl2br(htmlspecialchars($v['job_description'] ?? 'No description available')) ?></p>
                                            </div>
                                            
                                            <div class="details-section">
                                                <h5>Application Requirements</h5>
                                                <div class="requirements-list">
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-file-alt"></i> Application Letter</strong>
                                                        <p>Formal letter expressing interest in the position</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-file-signature"></i> Letter of Intent and Statement of Authenticity</strong>
                                                        <p>Formal letter of intent with statement of authenticity and veracity of documents submitted</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-graduation-cap"></i> Fully Accomplished and Notarized Transcript of Records</strong>
                                                        <p>(if applicable) Complete and certified academic record</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-briefcase"></i> Certified True Copy of Updated Service Record or Certificate of Employment</strong>
                                                        <p>(if applicable) Official employment record</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-certificate"></i> Certified True Copy of Certificates of Training/Seminars Attended</strong>
                                                        <p>Official proof of professional development activities</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-chart-line"></i> Certified True Copy of Performance Rating</strong>
                                                        <p>(if applicable) Last two rating periods performance evaluation</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-file-contract"></i> Photocopy of Latest Appointment</strong>
                                                        <p>(if applicable) Current appointment document</p>
                                                    </div>
                                                    
                                                    <div class="requirement-item">
                                                        <strong><i class="fas fa-folder"></i> Other Documents Relevant to the Position Applied For</strong>
                                                        <p>Any additional documents supporting your application</p>
                                                    </div>
                                                </div>
                                                
                                                <div class="requirement-note">
                                                    <i class="fas fa-info-circle"></i> Note: Please ensure all documents are properly certified and notarized where required. Submit all documents as PDF files.
                                                </div>
                                            </div>
                                            
                                            <div class="details-section">
                                                <h5>Additional Information</h5>
                                                <p><strong>Slots Available:</strong> <?= htmlspecialchars($v['candidate_slot'] ?? 'N/A') ?></p>
                                                <p><strong>Application Deadline:</strong> <?= date('F j, Y', strtotime($v['application_deadline'] ?? 'now')) ?></p>
                                                <p><strong>Days Left:</strong> <span class="days-left-badge"><?= $daysLeft ?> days</span></p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($u && isset($u['role']) && $u['role'] === 'applicant'): ?>
    <div class="apply-form">
        <form action="apply.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="vacancy_id" value="<?= htmlspecialchars($v['vacancy_id'] ?? '') ?>">
            
            <div class="details-grid">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-file-alt"></i> Application Letter <span class="required">*</span></label>
                    <input type="file" name="application_letter" class="form-input" required accept=".pdf,.doc,.docx">
                    <small class="form-text">Formal letter expressing interest in the position</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-file-signature"></i> Letter of Intent and Statement of Authenticity <span class="required">*</span></label>
                    <input type="file" name="letter_of_intent" class="form-input" required accept=".pdf,.doc,.docx">
                    <small class="form-text">With statement of authenticity and veracity of documents</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-graduation-cap"></i> Transcript of Records</label>
                    <input type="file" name="transcript" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Fully accomplished and notarized (if applicable)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-briefcase"></i> Service Record / Certificate of Employment</label>
                    <input type="file" name="service_record" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Certified true copy, updated (if applicable)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-certificate"></i> Training/Seminar Certificates</label>
                    <input type="file" name="training_certificates" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Certified true copies</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-chart-line"></i> Performance Rating</label>
                    <input type="file" name="performance_rating" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Last two rating periods (if applicable)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-file-contract"></i> Latest Appointment</label>
                    <input type="file" name="appointment" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Photocopy (if applicable)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-folder"></i> Other Relevant Documents</label>
                    <input type="file" name="other_documents" class="form-input" accept=".pdf,.doc,.docx">
                    <small class="form-text">Any additional documents supporting your application</small>
                </div>
            </div>
            
            <button type="submit" class="apply-button">
                <i class="fas fa-paper-plane"></i> Submit Application
            </button>
        </form>
    </div>
<?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if ($activeVacanciesCount === 0): ?>
                            <tr class="no-results-row">
                                <td colspan="5">
                                    <div class="no-results">
                                        <div class="no-results-icon"><i class="fas fa-briefcase"></i></div>
                                        <h4>No Active Job Vacancies</h4>
                                        <p>All current job openings have expired or been filled.</p>
                                        <p>Please check back later for new opportunities.</p>
                                        <a href="index.php" class="back-link">
                                            <i class="fas fa-home"></i> Back to Home
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
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
            console.log("DEBUG: JavaScript loaded successfully");
            
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const viewToggle = document.getElementById('viewToggle');
            const vacanciesBody = document.getElementById('vacanciesBody');
            const resultCount = document.getElementById('resultCount');
            const noResults = document.getElementById('noResults');
            
            // Get initial rows and filter out expired ones
            const originalRows = Array.from(vacanciesBody.querySelectorAll('.vacancy-row')).filter(row => {
                const deadlineStr = row.getAttribute('data-deadline');
                if (!deadlineStr) return false;
                
                const deadline = new Date(deadlineStr);
                const now = new Date();
                return deadline > now;
            });
            
            console.log("DEBUG: Found " + originalRows.length + " original rows");
            
            // Set initial count
            resultCount.textContent = originalRows.length;
            
            // Toggle details functionality
            function attachToggleListeners() {
                document.querySelectorAll('.toggle-details-btn').forEach(button => {
                    button.removeEventListener('click', handleToggleClick);
                    button.addEventListener('click', handleToggleClick);
                });
            }
            
            function handleToggleClick() {
                const id = this.getAttribute('data-id');
                const detailsRow = document.getElementById('details-' + id);
                const icon = this.querySelector('i');
                
                if (!detailsRow) {
                    console.log("DEBUG: No details row found for id: " + id);
                    return;
                }
                
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
            }
            
            // Update deadline badges and filter expired
            function updateDeadlineBadges() {
                const now = new Date();
                const rows = document.querySelectorAll('.vacancy-row');
                
                rows.forEach(row => {
                    const deadlineStr = row.getAttribute('data-deadline');
                    if (deadlineStr) {
                        const deadline = new Date(deadlineStr);
                        const daysLeft = Math.ceil((deadline - now) / (1000 * 60 * 60 * 24));
                        
                        // Update days left in the data attribute
                        row.setAttribute('data-days-left', daysLeft);
                        
                        // Find and update the badge
                        const badge = row.querySelector('.deadline-badge');
                        if (badge) {
                            badge.classList.remove('urgent', 'normal', 'expired-badge');
                            
                            if (daysLeft <= 0) {
                                badge.classList.add('expired-badge');
                                badge.innerHTML = '<i class="fas fa-ban"></i> Expired';
                                row.classList.add('expired-row');
                                
                                // Disable the view details button
                                const btn = row.querySelector('.toggle-details-btn');
                                if (btn) {
                                    btn.classList.add('disabled-btn');
                                    btn.innerHTML = '<i class="fas fa-ban"></i> Expired';
                                }
                            } else if (daysLeft <= 3) {
                                badge.classList.add('urgent');
                            } else {
                                badge.classList.add('normal');
                            }
                        }
                    }
                });
            }
            
            // Filter and sort vacancies
            function filterAndSortVacancies() {
                const searchTerm = searchInput.value.toLowerCase();
                const sortValue = sortSelect.value;
                const viewValue = viewToggle.value;
                
                let filtered = [...originalRows];
                const now = new Date();
                
                // Remove expired rows from filtered results
                filtered = filtered.filter(row => {
                    const deadlineStr = row.getAttribute('data-deadline');
                    if (!deadlineStr) return false;
                    
                    const deadline = new Date(deadlineStr);
                    return deadline > now;
                });
                
                // Filter by search
                if (searchTerm) {
                    filtered = filtered.filter(row => {
                        const title = row.getAttribute('data-title') || '';
                        return title.includes(searchTerm);
                    });
                }
                
                // Filter by view
                if (viewValue === 'recent') {
                    const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    filtered = filtered.filter(row => {
                        const datePosted = new Date(row.getAttribute('data-date'));
                        return datePosted >= sevenDaysAgo;
                    });
                } else if (viewValue === 'urgent') {
                    filtered = filtered.filter(row => {
                        const daysLeft = parseInt(row.getAttribute('data-days-left') || 999);
                        return daysLeft <= 3 && daysLeft > 0;
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
                        if (detailsRow) {
                            vacanciesBody.appendChild(row);
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
                
                // Reattach event listeners
                attachToggleListeners();
            }
            
            // Initial setup
            attachToggleListeners();
            updateDeadlineBadges();
            
            // Set initial result count
            const initialFilteredRows = [...originalRows].filter(row => {
                const deadlineStr = row.getAttribute('data-deadline');
                if (!deadlineStr) return false;
                
                const deadline = new Date(deadlineStr);
                return deadline > new Date();
            });
            resultCount.textContent = initialFilteredRows.length;
            
            // Event listeners
            searchInput.addEventListener('input', filterAndSortVacancies);
            sortSelect.addEventListener('change', filterAndSortVacancies);
            viewToggle.addEventListener('change', filterAndSortVacancies);
            
            console.log("DEBUG: Event listeners attached");
        });
    </script>
</body>
</html>