-- =============================================================================
--  International Conference — MySQL/MariaDB Schema
-- =============================================================================
--  Import this file from phpMyAdmin on a fresh install.
--  Then open /install/create-admin.php once.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- =============================================================================
--  USERS
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','organizer') NOT NULL DEFAULT 'organizer',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  LOGIN ATTEMPTS (rate limiting)
-- =============================================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_time (email,attempted_at),
  INDEX idx_login_ip_time (ip_address,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  ORGANIZERS
-- =============================================================================
CREATE TABLE IF NOT EXISTS organizers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  organization_name VARCHAR(190) NOT NULL,
  contact_person VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  website VARCHAR(500) NULL,
  about_organization TEXT NULL,
  logo VARCHAR(500) NULL,
  cover_image VARCHAR(500) NULL,
  country VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_suspended TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_profile_complete TINYINT(1) NOT NULL DEFAULT 0,
  slug VARCHAR(220) NOT NULL UNIQUE,
  twitter VARCHAR(500) NULL,
  linkedin VARCHAR(500) NULL,
  facebook VARCHAR(500) NULL,
  instagram VARCHAR(500) NULL,
  youtube VARCHAR(500) NULL,
  whatsapp VARCHAR(500) NULL,
  telegram VARCHAR(500) NULL,
  tiktok VARCHAR(500) NULL,
  github VARCHAR(500) NULL,
  pinterest VARCHAR(500) NULL,
  gallery_images JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_organizers_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,
  INDEX idx_organizers_public (
    is_profile_complete,
    is_suspended,
    is_verified
  ),
  INDEX idx_organizers_name (organization_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  PASSWORD RESETS
-- =============================================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  organizer_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,

  email VARCHAR(190) NOT NULL,

  token_hash CHAR(64) NOT NULL,

  expires_at DATETIME NOT NULL,

  used TINYINT(1) NOT NULL DEFAULT 0,
  used_at DATETIME NULL,

  invalidated TINYINT(1) NOT NULL DEFAULT 0,

  created_by BIGINT UNSIGNED NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_password_resets_organizer
    FOREIGN KEY (organizer_id)
    REFERENCES organizers(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_password_resets_created_by
    FOREIGN KEY (created_by)
    REFERENCES users(id)
    ON DELETE SET NULL,

  UNIQUE KEY uq_password_reset_token (token_hash),

  INDEX idx_password_reset_user (user_id),
  INDEX idx_password_reset_organizer (organizer_id),
  INDEX idx_password_reset_email (email),
  INDEX idx_password_reset_status (
    used,
    invalidated,
    expires_at
  ),
  INDEX idx_password_reset_created (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  CATEGORIES (topics)
-- =============================================================================
CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  slug VARCHAR(170) NOT NULL UNIQUE,
  description TEXT NULL,
  icon VARCHAR(80) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  COUNTRIES
-- =============================================================================
CREATE TABLE IF NOT EXISTS countries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(140) NOT NULL UNIQUE,
  code VARCHAR(10) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  CITIES
-- =============================================================================
CREATE TABLE IF NOT EXISTS cities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  time_zone VARCHAR(100) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_cities_country
    FOREIGN KEY (country_id)
    REFERENCES countries(id)
    ON DELETE CASCADE,

  UNIQUE KEY uq_city_country (country_id,name),
  UNIQUE KEY uq_city_slug_country (country_id,slug),

  INDEX idx_cities_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  CONFERENCES
-- =============================================================================
CREATE TABLE IF NOT EXISTS conferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  organizer_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  country_id BIGINT UNSIGNED NULL,
  city_id BIGINT UNSIGNED NULL,

  title VARCHAR(300) NOT NULL,
  short_title VARCHAR(120) NULL,

  slug VARCHAR(330) NOT NULL UNIQUE,

  banner_image VARCHAR(800) NULL,

  description LONGTEXT NOT NULL,

  state VARCHAR(150) NULL,
  venue VARCHAR(500) NULL,

  start_date DATE NOT NULL,
  end_date DATE NOT NULL,

  conference_time TIME NULL,
  time_zone VARCHAR(100) NULL,

  attendance_type ENUM('Online','Offline','Hybrid')
    NOT NULL DEFAULT 'Offline',

  organizer_website VARCHAR(500) NULL,
  conference_website VARCHAR(500) NOT NULL,
  registration_link VARCHAR(500) NULL,

  contact_email VARCHAR(190) NOT NULL,

  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(320) NULL,
  seo_keywords VARCHAR(700) NULL,

  status ENUM(
    'draft',
    'pending',
    'approved',
    'rejected'
  ) NOT NULL DEFAULT 'pending',

  rejection_reason TEXT NULL,

  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_deactivated TINYINT(1) NOT NULL DEFAULT 0,

  views_count INT UNSIGNED NOT NULL DEFAULT 0,
  registration_clicks INT UNSIGNED NOT NULL DEFAULT 0,

  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_conf_organizer
    FOREIGN KEY (organizer_id)
    REFERENCES organizers(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_conf_category
    FOREIGN KEY (category_id)
    REFERENCES categories(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_conf_country
    FOREIGN KEY (country_id)
    REFERENCES countries(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_conf_city
    FOREIGN KEY (city_id)
    REFERENCES cities(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_conf_admin
    FOREIGN KEY (approved_by)
    REFERENCES users(id)
    ON DELETE SET NULL,

  INDEX idx_conf_public (
    status,
    is_deactivated,
    start_date,
    end_date
  ),

  INDEX idx_conf_featured (
    is_featured,
    status,
    end_date
  ),

  INDEX idx_conf_org_status (
    organizer_id,
    status
  ),

  INDEX idx_conf_category (
    category_id,
    status
  ),

  INDEX idx_conf_location (
    country_id,
    city_id,
    status
  ),

  FULLTEXT KEY ft_conf_search (
    title,
    description
  )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  USER FEEDBACK
-- =============================================================================
CREATE TABLE IF NOT EXISTS user_feedbacks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  organization VARCHAR(190) NULL,
  country VARCHAR(120) NULL,

  image VARCHAR(800) NULL,

  message TEXT NOT NULL,

  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,

  status ENUM(
    'pending',
    'approved',
    'rejected'
  ) NOT NULL DEFAULT 'pending',

  is_verified TINYINT(1) NOT NULL DEFAULT 0,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_feedback_public (
    status,
    is_verified,
    created_at
  )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  SUBSCRIBER EMAILS
-- =============================================================================
CREATE TABLE IF NOT EXISTS subscriber_emails (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  email VARCHAR(190) NOT NULL UNIQUE,

  status ENUM(
    'active',
    'inactive'
  ) NOT NULL DEFAULT 'active',

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  MEDIA PARTNERS
-- =============================================================================
CREATE TABLE IF NOT EXISTS media_partners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name VARCHAR(190) NOT NULL,

  title VARCHAR(190)
    NULL DEFAULT 'Media Distribution & Outreach Partner',

  type VARCHAR(120)
    NULL DEFAULT 'Media Partner',

  description VARCHAR(500) NOT NULL,

  logo VARCHAR(800) NULL,

  website VARCHAR(500) NOT NULL,

  email VARCHAR(190) NULL,

  status ENUM(
    'pending',
    'approved',
    'rejected'
  ) NOT NULL DEFAULT 'pending',

  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_deactivated TINYINT(1) NOT NULL DEFAULT 0,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_media_public (
    status,
    is_deactivated,
    is_verified
  )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  ASSOCIATES
-- =============================================================================
CREATE TABLE IF NOT EXISTS associates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name VARCHAR(190) NOT NULL,

  title VARCHAR(190)
    NULL DEFAULT 'Academic & Scientific Associate',

  category VARCHAR(120)
    NULL DEFAULT 'Associates',

  description VARCHAR(500) NOT NULL,

  logo VARCHAR(800) NULL,

  website VARCHAR(500) NOT NULL,

  email VARCHAR(190) NULL,

  status ENUM(
    'pending',
    'approved',
    'rejected'
  ) NOT NULL DEFAULT 'pending',

  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_deactivated TINYINT(1) NOT NULL DEFAULT 0,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_assoc_public (
    status,
    is_deactivated,
    is_verified
  )

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  SITE CONTENT (editable via Admin → Content)
-- =============================================================================
CREATE TABLE IF NOT EXISTS site_content (
  content_key VARCHAR(100) PRIMARY KEY,

  content_value LONGTEXT NOT NULL,

  updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  SITE SETTINGS (contact info, social URLs)
-- =============================================================================
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(100) PRIMARY KEY,

  setting_value TEXT NULL,

  updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  BLOG POSTS (SIMPLIFIED)
-- =============================================================================
--  Simple blog structure:
--    - Title (required, auto-generates slug)
--    - Featured Image (uploaded)
--    - Description / Content (required)
--    - Author (person or company name)
--    - Created at (auto)
--  No status, no featured flag, no category, no read time.
-- =============================================================================
CREATE TABLE IF NOT EXISTS blog_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  title VARCHAR(255) NOT NULL,

  slug VARCHAR(280) NOT NULL UNIQUE,

  content LONGTEXT NOT NULL,

  featured_image VARCHAR(800) NULL,

  author VARCHAR(150) NOT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_blog_created (created_at),
  INDEX idx_blog_slug (slug)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
--  DEFAULT CONTENT SEEDS
-- =============================================================================
INSERT IGNORE INTO site_content (
  content_key,
  content_value
) VALUES

(
  'home_description',
  'Discover verified international conferences across academic, scientific, engineering, medical, business and professional fields. Explore topics, countries, cities and trusted organizers from one global directory.'
),

(
  'conference_description',
  'Browse upcoming approved international conferences. Search by topic, country, city, date and attendance type to find the right conference for your research, career and professional network.'
),

(
  'about_title',
  'About International Conference'
),

(
  'about_body',
  'International Conference is a global academic and professional resource designed to index and present legitimate conferences worldwide. We help researchers, students, professionals and organizations discover conference opportunities through structured listings and administrator review.\n\nOrganizer submissions pass through an approval workflow before publication. Our goal is to make conference discovery clearer, more useful and easier to verify.'
),

(
  'footer_description',
  'A global conference directory helping researchers, professionals and organizers discover trusted international conferences.'
),

(
  'privacy_policy',
  'At International Conference, we are committed to safeguarding your privacy. Information submitted through organizer accounts, conference submissions, partnership applications, contact forms and feedback forms is used to operate and improve the service. We apply reasonable technical and administrative safeguards and do not publish private account passwords.'
),

(
  'terms_of_service',
  'Organizers are responsible for providing accurate conference information. International Conference may review, approve, reject, deactivate or remove listings that are incomplete, misleading, duplicated or inconsistent with platform standards. The directory is an information service and is not responsible for changes made by third-party conference organizers.'
);


INSERT IGNORE INTO site_settings (
  setting_key,
  setting_value
) VALUES

('contact_email',''),
('contact_phone',''),
('contact_address',''),
('facebook_url',''),
('instagram_url',''),
('linkedin_url',''),
('twitter_url',''),
('youtube_url','');


INSERT IGNORE INTO categories (
  name,
  slug,
  description,
  status
) VALUES

(
  'Computer Science',
  'computer-science',
  'Conferences in computing, software, data and digital systems.',
  'active'
),

(
  'Engineering',
  'engineering',
  'Engineering and applied technology conferences.',
  'active'
),

(
  'Medicine & Health',
  'medicine-health',
  'Medical, clinical, healthcare and life science conferences.',
  'active'
),

(
  'Business & Management',
  'business-management',
  'Business, economics, finance and management conferences.',
  'active'
),

(
  'Education',
  'education',
  'Education, teaching and learning conferences.',
  'active'
),

(
  'Science & Research',
  'science-research',
  'Multidisciplinary science and research conferences.',
  'active'
);


-- =============================================================================
--  END OF SCHEMA
-- =============================================================================