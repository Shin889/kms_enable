<?php require_once __DIR__ . '/../src/init.php';
require_role(['admin']);
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($_POST['action'] === 'create') {
    db()->prepare("INSERT INTO job_vacancies (posted_by, job_title, job_description, skills_required, application_deadline, status)
                   VALUES (?, ?, ?, ?, ?, ?)")
      ->execute([$_SESSION['uid'], $_POST['job_title'], $_POST['job_description'], $_POST['skills_required'] ?: null, $_POST['application_deadline'] ?: null, $_POST['status'] ?? 'open']);
  } elseif ($_POST['action'] === 'update') {
    db()->prepare("UPDATE job_vacancies SET job_title=?, job_description=?, skills_required=?, application_deadline=?, status=? WHERE vacancy_id=?")
      ->execute([$_POST['job_title'], $_POST['job_description'], $_POST['skills_required'] ?: null, $_POST['application_deadline'] ?: null, $_POST['status'] ?? 'open', (int) $_POST['vacancy_id']]);
  } elseif ($_POST['action'] === 'delete') {
    db()->prepare("DELETE FROM job_vacancies WHERE vacancy_id=?")->execute([(int) $_POST['vacancy_id']]);
  }
  redirect('vacancy_manage.php');
}

$list = db()->query("SELECT * FROM job_vacancies ORDER BY date_posted DESC")->fetchAll();
?>
<!doctype html>
<html>

<body>
  <link rel="stylesheet" type="text/css" href="assets/utils/vacancy_manage.css">
  <h3>Manage Vacancies</h3>

  <div class="form-box">
    <h4>Create Vacancy</h4>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="create">

      <label>Title
        <input type="text" name="job_title" required>
      </label>

      <label>Description
        <textarea name="job_description" required></textarea>
      </label>

      <label>Skills (JSON or text)
        <textarea name="skills_required"></textarea>
      </label>

      <label>Deadline
        <input type="date" name="application_deadline">
      </label>

      <label>Status
        <select name="status">
          <option>open</option>
          <option>closed</option>
        </select>
      </label>

      <button type="submit">Create</button>
    </form>
  </div>

  <h4>Existing Vacancies</h4>
  <?php foreach ($list as $v): ?>
    <div class="form-box">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">
        <input type="hidden" name="action" value="update">

        <label>Title
          <input type="text" name="job_title" value="<?= htmlspecialchars($v['job_title']) ?>">
        </label>

        <label>Description
          <textarea name="job_description"><?= htmlspecialchars($v['job_description']) ?></textarea>
        </label>

        <label>Skills
          <textarea name="skills_required"><?= htmlspecialchars($v['skills_required']) ?></textarea>
        </label>

        <label>Deadline
          <input type="date" name="application_deadline" value="<?= htmlspecialchars($v['application_deadline']) ?>">
        </label>

        <label>Status
          <select name="status">
            <option <?= $v['status'] === 'open' ? 'selected' : '' ?>>open</option>
            <option <?= $v['status'] === 'closed' ? 'selected' : '' ?>>closed</option>
          </select>
        </label>

        <button type="submit">Update</button>
      </form>

      <form method="post" onsubmit="return confirm('Delete vacancy?');">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="vacancy_id" value="<?= $v['vacancy_id'] ?>">
        <input type="hidden" name="action" value="delete">
        <button class="danger">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</body>

</html>