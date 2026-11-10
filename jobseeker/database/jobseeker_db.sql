-- JobSeeker database  |  import with phpMyAdmin (Import tab) or: mysql -u root < jobseeker_db.sql
-- All demo accounts use the password:  Password@123
CREATE DATABASE IF NOT EXISTS jobseeker_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobseeker_db;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS system_settings, password_resets, activity_logs, announcements, interviews, notifications, saved_jobs, applications, jobs, job_categories, staff, companies, user_profiles, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('seeker','staff','admin') NOT NULL DEFAULT 'seeker',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  failed_attempts TINYINT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role), INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  phone VARCHAR(30), location VARCHAR(100), headline VARCHAR(150), skills VARCHAR(255), bio TEXT,
  profile_picture VARCHAR(255), resume VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL, industry VARCHAR(100), location VARCHAR(100), website VARCHAR(150), description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB;

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE, company_id INT NULL, position VARCHAR(100), department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE job_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL, category_id INT NULL, posted_by INT NULL,
  title VARCHAR(150) NOT NULL, description TEXT NOT NULL, requirements TEXT,
  location VARCHAR(100) NOT NULL,
  job_type ENUM('Full Time','Part Time','Contract','Internship') NOT NULL DEFAULT 'Full Time',
  salary_min INT NULL, salary_max INT NULL,
  status ENUM('open','closed','draft') NOT NULL DEFAULT 'open',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  deadline DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status), INDEX idx_location (location), INDEX idx_type (job_type), FULLTEXT idx_search (title, description),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL, user_id INT NOT NULL,
  cover_letter TEXT, resume VARCHAR(255),
  status ENUM('Pending','Under Review','Shortlisted','Interview','Rejected','Hired') NOT NULL DEFAULT 'Pending',
  staff_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_user (job_id, user_id), INDEX idx_status (status),
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE saved_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, job_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_saved (user_id, job_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, title VARCHAR(150) NOT NULL, message TEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_read (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE interviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL, scheduled_at DATETIME NOT NULL, location VARCHAR(200), notes TEXT, created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_when (scheduled_at),
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL, body TEXT NOT NULL, created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL, action VARCHAR(100) NOT NULL, details VARCHAR(255), ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email), INDEX idx_token (token_hash)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) NOT NULL UNIQUE, setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= SAMPLE DATA (password for everyone: Password@123) =================
SET @pw = '$2b$10$GpoK2S9uqNHW4DVqikV3z.b3SgdoBedJ8Pz0mlc5tDLhG8Fnxyhea';
INSERT INTO users (id, full_name, username, email, password, role, created_at) VALUES
(1,'Alex Rivera','admin','admin@jobseeker.com',@pw,'admin','2026-01-05 09:00:00'),
(2,'Maria Santos','maria','maria@jobseeker.com',@pw,'staff','2026-02-10 09:00:00'),
(3,'Paolo Reyes','paolo','paolo@jobseeker.com',@pw,'staff','2026-03-12 09:00:00'),
(4,'Juan Dela Cruz','juan','juan@example.com',@pw,'seeker','2026-03-20 10:00:00'),
(5,'Ana Lopez','ana','ana@example.com',@pw,'seeker','2026-05-02 10:00:00'),
(6,'Carlo Mendoza','carlo','carlo@example.com',@pw,'seeker','2026-06-18 10:00:00'),
(7,'Liza Garcia','liza','liza@example.com',@pw,'seeker','2026-08-07 10:00:00'),
(8,'Ramon Padilla','ramon','ramon@example.com',@pw,'seeker','2026-10-01 10:00:00');

INSERT INTO user_profiles (user_id, phone, location, headline, skills, bio) VALUES
(1,'0917 000 0001','Manila','System Administrator','','Platform administrator.'),
(2,'0917 000 0002','Manila','HR Officer','Recruitment','Recruitment lead at TechSolutions.'),
(3,'0917 000 0003','Cebu','Recruitment Specialist','Recruitment','Handles BPO hiring.'),
(4,'0917 111 2222','Manila','Junior Web Developer','HTML, CSS, JavaScript, PHP, React','Passionate about building clean, accessible websites.'),
(5,'0917 111 3333','Cebu','Customer Support Associate','Communication, CRM','Friendly and detail-oriented.'),
(6,'0917 111 4444','Davao','Graphic Designer','Photoshop, Illustrator, Figma','Creative designer with 3 years experience.'),
(7,'0917 111 5555','Quezon City','Marketing Assistant','SEO, Social Media','Digital marketing graduate.'),
(8,'0917 111 6666','Manila','IT Support Specialist','Networking, Windows, Helpdesk','Certified IT support technician.');

INSERT INTO companies (id, name, industry, location, website, description) VALUES
(1,'TechSolutions Inc.','IT & Software','Manila','https://example.com','Software house building web and mobile products.'),
(2,'Global Call Center','BPO','Cebu','https://example.com','Customer experience and outsourcing provider.'),
(3,'Creative Hub','Design','Davao','https://example.com','Design and branding studio.'),
(4,'NexTech Solutions','IT & Software','Manila','https://example.com','Managed IT services and support.'),
(5,'Bright Media','Marketing','Quezon City','https://example.com','Digital marketing agency.'),
(6,'HealthFirst Clinic','Healthcare','Cebu','https://example.com','Multi-specialty community clinic.');

INSERT INTO staff (user_id, company_id, position, department) VALUES (2,1,'HR Officer','Human Resources'),(3,2,'Recruitment Specialist','Talent Acquisition');

INSERT INTO job_categories (id, name) VALUES (1,'IT & Software'),(2,'Marketing'),(3,'Design'),(4,'Customer Service'),(5,'Education'),(6,'Healthcare'),(7,'Finance'),(8,'Engineering');

INSERT INTO jobs (id, company_id, category_id, posted_by, title, description, requirements, location, job_type, salary_min, salary_max, status, is_featured, deadline, created_at) VALUES
(1,1,1,2,'Frontend Developer','We are looking for a skilled Frontend Developer to join our growing team. You will build responsive interfaces and work closely with designers and backend engineers.','2+ years of experience in React or Vue\nStrong HTML, CSS and JavaScript\nBasic understanding of UI/UX','Manila','Full Time',30000,50000,'open',1,'2027-03-31','2026-10-20 09:00:00'),
(2,2,4,3,'Customer Service Representative','Handle inbound customer inquiries via phone and chat for our international clients. Training provided.','Good English communication skills\nWilling to work shifting schedules\nHigh school graduate or higher','Cebu','Full Time',18000,25000,'open',1,'2027-02-28','2026-10-18 09:00:00'),
(3,3,3,1,'Graphic Designer','Create engaging visuals for brands across print and digital campaigns.','Portfolio of previous work\nProficient in Photoshop and Illustrator\nCreative mindset','Davao','Full Time',20000,30000,'open',1,'2027-04-15','2026-10-15 09:00:00'),
(4,4,1,2,'IT Support Specialist','Provide first and second-level technical support to internal users and clients.','Knowledge of Windows and networking basics\nGood troubleshooting skills\nCertification is a plus','Manila','Full Time',22000,35000,'open',1,'2027-03-15','2026-10-12 09:00:00'),
(5,1,1,2,'Web Developer','Develop and maintain PHP/MySQL web applications for our clients.','Experience with PHP and MySQL\nFamiliar with Bootstrap\nKnowledge of Git','Manila','Full Time',30000,50000,'open',0,'2027-03-20','2026-10-10 09:00:00'),
(6,5,2,1,'Marketing Assistant','Support campaign planning, social media and content creation.','Degree in Marketing or related field\nStrong writing skills\nFamiliar with social media tools','Quezon City','Full Time',18000,25000,'open',0,'2027-02-15','2026-10-08 09:00:00'),
(7,2,4,3,'Customer Support Specialist','Support chat and email customers for a global e-commerce client.','Excellent communication skills\nPrior BPO experience preferred','Cebu','Part Time',16000,25000,'open',0,'2027-03-01','2026-10-05 09:00:00'),
(8,6,6,1,'Clinic Nurse','Provide patient care and assist physicians in daily clinic operations.','Licensed nurse\nAt least 1 year of clinical experience','Cebu','Full Time',25000,35000,'open',0,'2027-04-01','2026-09-28 09:00:00'),
(9,4,8,2,'Network Engineer Intern','Assist our engineers with network installation and monitoring.','Currently enrolled in IT or Engineering\nBasic networking knowledge','Manila','Internship',8000,12000,'open',0,'2027-01-31','2026-09-25 09:00:00'),
(10,3,3,1,'UI/UX Designer','Design intuitive web and mobile interfaces from research to hi-fi prototypes.','Figma proficiency\nPortfolio required','Davao','Contract',35000,55000,'open',0,'2027-03-10','2026-09-20 09:00:00'),
(11,5,7,1,'Accounting Staff','Prepare financial records, reconcile accounts and assist with audits.','Accountancy graduate\nKnowledge of accounting software','Quezon City','Full Time',22000,30000,'open',0,'2027-02-20','2026-09-15 09:00:00'),
(12,1,5,2,'Online English Tutor','Teach conversational English to students online.','Fluent English\nTeaching certificate is a plus','Manila','Part Time',12000,20000,'open',0,'2027-05-01','2026-09-10 09:00:00'),
(13,4,1,2,'Junior Data Analyst','Clean, analyze and visualize data for business reports.','Excel and SQL basics\nAnalytical mindset','Manila','Full Time',25000,38000,'draft',0,'2027-04-30','2026-10-25 09:00:00'),
(14,2,4,3,'Technical Support Agent (Expired)','Technical support role for a telecom client.','Good communication','Cebu','Full Time',19000,26000,'open',0,'2026-09-01','2026-08-01 09:00:00');

INSERT INTO applications (id, job_id, user_id, cover_letter, status, created_at) VALUES
(1,1,4,'I would love to join your frontend team.','Under Review','2026-10-23 10:00:00'),
(2,2,4,'I have strong communication skills.','Under Review','2026-10-22 10:00:00'),
(3,7,4,'Interested in this support role.','Under Review','2026-10-21 10:00:00'),
(4,4,4,'I have helpdesk experience.','Shortlisted','2026-10-20 10:00:00'),
(5,3,4,'I also do basic design work.','Pending','2026-10-19 10:00:00'),
(6,1,5,'Please consider my application.','Shortlisted','2026-10-22 11:00:00'),
(7,3,5,'Eager to grow as a designer.','Interview','2026-10-21 11:00:00'),
(8,4,6,'Experienced IT professional.','Interview','2026-10-20 11:00:00'),
(9,5,7,'Looking for a web role.','Rejected','2026-10-19 11:00:00'),
(10,1,8,'Frontend is my passion.','Under Review','2026-10-24 11:00:00'),
(11,6,6,'Marketing is a great fit for my skills.','Hired','2026-10-10 11:00:00'),
(12,2,7,'Fast learner and team player.','Pending','2026-10-26 11:00:00');

INSERT INTO saved_jobs (user_id, job_id) VALUES (4,3),(4,6),(4,10),(5,1);
INSERT INTO interviews (application_id, scheduled_at, location, notes, created_by) VALUES
(7,'2026-11-12 10:00:00','Creative Hub Office, Davao','Bring your portfolio.',1),
(8,'2026-11-14 14:00:00','Zoom (link will be emailed)','Technical interview.',2);

INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES
(4,'Application under review','Your application for Frontend Developer is now Under Review.',0,NOW() - INTERVAL 2 HOUR),
(4,'Shortlisted','You were shortlisted for IT Support Specialist.',0,NOW() - INTERVAL 5 HOUR),
(4,'Welcome to JobSeeker','Complete your profile to get better job matches.',1,NOW() - INTERVAL 3 DAY),
(2,'New application','Juan Dela Cruz applied for Frontend Developer.',0,NOW() - INTERVAL 1 DAY),
(1,'System ready','JobSeeker has been installed successfully.',0,NOW() - INTERVAL 1 DAY);

INSERT INTO announcements (title, body, created_by, created_at) VALUES
('Career Fair 2026','Join our virtual career fair on December 5. Over 40 companies are hiring!',2,NOW() - INTERVAL 2 DAY),
('Resume Tips Webinar','Free webinar on writing a winning resume this Friday at 3 PM.',2,NOW() - INTERVAL 6 DAY);

INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES
(8,'User registered','ramon@example.com','127.0.0.1',NOW() - INTERVAL 2 HOUR),
(1,'Staff account created','Maria Santos','127.0.0.1',NOW() - INTERVAL 5 HOUR),
(2,'Job posted','Frontend Developer','127.0.0.1',NOW() - INTERVAL 1 DAY),
(1,'Job approved','Web Developer','127.0.0.1',NOW() - INTERVAL 1 DAY),
(1,'System backup completed','Automated','127.0.0.1',NOW() - INTERVAL 2 DAY);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('site_name','JobSeeker'),('contact_email','hello@jobseeker.com'),('contact_phone','+63 900 123 4567'),
('contact_address','Ayala Avenue, Makati City, Philippines'),('email_notifications','1'),('allow_registration','1'),('jobs_per_page','6');
