CREATE DATABASE IF NOT EXISTS kms_recruithub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kms_recruithub;

-- USERS
CREATE TABLE IF NOT EXISTS users (
  uid CHAR(36) PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('applicant','clerk','admin') NOT NULL,
  firstName VARCHAR(100),
  middleName VARCHAR(100),
  lastName VARCHAR(100),
  userName VARCHAR(100),
  account_status ENUM('active','pending','suspended') DEFAULT 'active',
  date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL
) ENGINE=InnoDB;

-- JOB VACANCIES
CREATE TABLE IF NOT EXISTS job_vacancies (
  vacancy_id INT AUTO_INCREMENT PRIMARY KEY,
  posted_by CHAR(36) NOT NULL,
  job_title VARCHAR(200) NOT NULL,
  job_description TEXT NOT NULL,
  skills_required JSON NULL,
  date_posted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  application_deadline DATE NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  CONSTRAINT fk_job_posted_by FOREIGN KEY (posted_by) REFERENCES users(uid)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- APPLICATIONS
CREATE TABLE IF NOT EXISTS applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  vacancy_id INT NOT NULL,
  applicant_uid CHAR(36) NOT NULL,
  date_applied DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resume_path VARCHAR(255),
  cover_letter_path VARCHAR(255),
  requirements_docs JSON NULL,
  status ENUM(
    'submitted',
    'approved_by_clerk','rejected_by_clerk',
    'approved_by_admin','rejected_by_admin',
    'interviewed','hired','rejected_final'
  ) NOT NULL DEFAULT 'submitted',
  approval_rejection_reason TEXT NULL,
  interview_date DATETIME NULL,
  CONSTRAINT fk_app_vac FOREIGN KEY (vacancy_id) REFERENCES job_vacancies(vacancy_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_app_user FOREIGN KEY (applicant_uid) REFERENCES users(uid)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- APPLICANT PROFILES
CREATE TABLE IF NOT EXISTS applicant_profiles (
  profile_id INT AUTO_INCREMENT PRIMARY KEY,
  applicant_uid CHAR(36) NOT NULL,
  skills JSON NULL,
  qualifications JSON NULL,
  other_details TEXT NULL,
  CONSTRAINT fk_profile_user FOREIGN KEY (applicant_uid) REFERENCES users(uid)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE (applicant_uid)
) ENGINE=InnoDB;

-- INTERVIEW QUALIFICATIONS
CREATE TABLE IF NOT EXISTS interview_qualifications (
  qualification_id INT AUTO_INCREMENT PRIMARY KEY,
  applicant_uid CHAR(36) NOT NULL,
  vacancy_id INT NOT NULL,
  qualifications_details TEXT NOT NULL,
  CONSTRAINT fk_iq_user FOREIGN KEY (applicant_uid) REFERENCES users(uid)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_iq_vac FOREIGN KEY (vacancy_id) REFERENCES job_vacancies(vacancy_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- EMPLOYEE TRACKING
CREATE TABLE IF NOT EXISTS employee_tracking (
  employee_id INT AUTO_INCREMENT PRIMARY KEY,
  applicant_uid CHAR(36) NOT NULL,
  employment_status ENUM('job_order','permanent','temporary','contractual') NOT NULL,
  start_date DATE NOT NULL,
  monitoring_start_date DATE NULL,
  promotion_history TEXT NULL,
  remarks TEXT NULL,
  CONSTRAINT fk_et_user FOREIGN KEY (applicant_uid) REFERENCES users(uid)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE (applicant_uid)
) ENGINE=InnoDB;

-- MESSAGES LOG
CREATE TABLE IF NOT EXISTS messages_log (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  recipient_uid CHAR(36) NOT NULL,
  sender_uid CHAR(36) NULL,
  message_type VARCHAR(100) NOT NULL,
  message_content TEXT NOT NULL,
  sent_via ENUM('system','gmail') NOT NULL DEFAULT 'system',
  sent_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_recipient FOREIGN KEY (recipient_uid) REFERENCES users(uid)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_uid) REFERENCES users(uid)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- PASSWORD RESETS
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(36) NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  INDEX (uid), INDEX (token),
  CONSTRAINT fk_pr_user FOREIGN KEY (uid) REFERENCES users(uid)
    ON DELETE CASCADE ON UPDATE CASCADE
);
