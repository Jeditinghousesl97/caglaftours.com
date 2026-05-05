-- Nayagara Tours - Database Schema
-- Database: nayagara_tours

CREATE DATABASE IF NOT EXISTS nayagara_tours
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nayagara_tours;


-- 1. Admin Users
CREATE TABLE IF NOT EXISTS admin_users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL,
  username    VARCHAR(50)   NOT NULL UNIQUE,
  email       VARCHAR(150)  NOT NULL UNIQUE,
  password    VARCHAR(255)  NOT NULL,
  role        ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin (username: admin / password: Admin@1234 — change after first login)
INSERT INTO admin_users (name, username, email, password, role) VALUES
('Super Admin', 'admin', 'admin@nayagaratours.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');



-- 2. Site Settings (key / value store)
CREATE TABLE IF NOT EXISTS settings (
  `key`       VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`     TEXT         DEFAULT NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (`key`, `value`) VALUES
('site_name',          'Nayagara Tours'),
('site_tagline',       'Sri Lanka Travel'),
('site_logo',          ''),
('theme_primary',      '#0077B6'),
('theme_primary_dark', '#005F92'),
('theme_primary_light','#00B4D8'),
('theme_secondary',    '#00B4D8'),
('theme_accent',       '#ADE8F4'),
('theme_dark',         '#03045E'),
('theme_dark_2',       '#023E8A'),
('theme_dark_3',       '#0A1628'),
('theme_light',        '#F0F9FF'),
('theme_light_2',      '#E8F4FD'),
('theme_text',         '#2D3748'),
('theme_text_light',   '#718096'),
('theme_text_muted',   '#A0AEC0'),
('theme_border',       '#E2E8F0'),
('contact_phone',      ''),
('contact_email',      ''),
('contact_whatsapp',   ''),
('contact_address',    ''),
('social_facebook',    ''),
('social_instagram',   ''),
('social_twitter',     ''),
('social_youtube',     ''),
('social_tripadvisor', ''),
('google_maps_embed',  ''),
('meta_description',   'Nayagara Tours Sri Lanka - handcrafted tour packages for every traveller.');



-- 3. Tour Packages
CREATE TABLE IF NOT EXISTS packages (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200)  NOT NULL,
  slug          VARCHAR(220)  NOT NULL UNIQUE,
  category      ENUM('cultural','beach','wildlife','hill','honeymoon','adventure') NOT NULL,
  badge         ENUM('popular','bestseller','new','limited','hotdeal') DEFAULT NULL,
  duration      VARCHAR(50)   NOT NULL,
  price         DECIMAL(10,2) DEFAULT NULL,
  old_price     DECIMAL(10,2) DEFAULT NULL,
  group_size    VARCHAR(50)   DEFAULT NULL,
  difficulty    ENUM('easy','moderate','challenging') DEFAULT 'moderate',
  best_season   VARCHAR(100)  DEFAULT NULL,
  rating        DECIMAL(2,1)  DEFAULT NULL,
  review_count  SMALLINT UNSIGNED DEFAULT 0,
  description   TEXT          NOT NULL,
  highlights    TEXT          DEFAULT NULL,
  itinerary     LONGTEXT      DEFAULT NULL,
  inclusions    TEXT          DEFAULT NULL,
  exclusions    TEXT          DEFAULT NULL,
  destinations  TEXT          DEFAULT NULL,
  cover_image   VARCHAR(300)  DEFAULT NULL,
  is_featured   TINYINT(1)    NOT NULL DEFAULT 0,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_itinerary_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  package_id    INT UNSIGNED NOT NULL,
  day_number    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  title         VARCHAR(255) NOT NULL,
  description   TEXT         DEFAULT NULL,
  image_1       VARCHAR(300) DEFAULT NULL,
  image_2       VARCHAR(300) DEFAULT NULL,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
) ENGINE=InnoDB;



-- 4. Services
CREATE TABLE IF NOT EXISTS services (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type        ENUM('core','additional') NOT NULL DEFAULT 'core',
  icon_class  VARCHAR(100) NOT NULL DEFAULT 'fa-star',
  title       VARCHAR(200) NOT NULL,
  description TEXT         DEFAULT NULL,
  features    TEXT         DEFAULT NULL,   -- newline-separated for core services
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS process_steps (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  step_number  SMALLINT UNSIGNED NOT NULL,
  icon_class   VARCHAR(100) NOT NULL DEFAULT 'fa-circle-question',
  title        VARCHAR(200) NOT NULL,
  description  TEXT DEFAULT NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO process_steps (step_number, icon_class, title, description, sort_order)
SELECT *
FROM (
  SELECT 1, 'fa-comment-dots', 'Tell Us Your Dream', 'Share your travel dates, budget, interests and any special requirements. The more you tell us, the better we can plan.', 1
  UNION ALL
  SELECT 2, 'fa-pencil-ruler', 'We Plan Your Trip', 'Our travel experts craft a personalised itinerary with flights, hotels, guides, and activities tailored just for you.', 2
  UNION ALL
  SELECT 3, 'fa-circle-check', 'Book & Confirm', 'Review your itinerary, request any tweaks, and confirm your booking. We handle all reservations and send you full confirmation.', 3
  UNION ALL
  SELECT 4, 'fa-sun', 'Enjoy Sri Lanka', 'Arrive, relax, and explore. Your driver meets you at the airport and our team is just a message away throughout your trip.', 4
) AS defaults_to_insert
WHERE NOT EXISTS (
  SELECT 1 FROM process_steps
);



-- 5. Hero Banners
CREATE TABLE IF NOT EXISTS hero_banners (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  heading     VARCHAR(250) NOT NULL,
  subheading  VARCHAR(350) DEFAULT NULL,
  image_path  VARCHAR(300) NOT NULL,
  btn_label   VARCHAR(100) DEFAULT 'Explore Now',
  btn_link    VARCHAR(300) DEFAULT '#packages',
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  is_active   TINYINT(1)  NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 6. Gallery Images
CREATE TABLE IF NOT EXISTS gallery (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) DEFAULT NULL,
  category    VARCHAR(100) DEFAULT NULL,
  image_path  VARCHAR(300) NOT NULL,
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 7. Gallery Videos
CREATE TABLE IF NOT EXISTS gallery_videos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(250) NOT NULL,
  description TEXT         DEFAULT NULL,
  video_type  ENUM('youtube','upload') NOT NULL DEFAULT 'youtube',
  youtube_url VARCHAR(500) DEFAULT NULL,   -- original URL the admin pasted
  embed_url   VARCHAR(500) DEFAULT NULL,   -- converted /embed/ URL for iframe
  video_file  VARCHAR(500) DEFAULT NULL,   -- path to uploaded video file
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 8. Blog Posts
CREATE TABLE IF NOT EXISTS blog_posts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(250) NOT NULL,
  slug          VARCHAR(270) NOT NULL UNIQUE,
  excerpt       TEXT         DEFAULT NULL,
  content       LONGTEXT     NOT NULL,
  cover_image   VARCHAR(300) DEFAULT NULL,
  category      VARCHAR(100) DEFAULT NULL,
  tags          VARCHAR(300) DEFAULT NULL,
  author        VARCHAR(150) DEFAULT NULL,
  is_published  TINYINT(1)   NOT NULL DEFAULT 0,
  published_at  DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 9. Reviews / Testimonials
CREATE TABLE IF NOT EXISTS reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  country     VARCHAR(100) DEFAULT NULL,
  rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  review_text TEXT         NOT NULL,
  avatar      VARCHAR(300) DEFAULT NULL,
  is_approved TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- 10. Bookings
CREATE TABLE IF NOT EXISTS bookings (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  package_id      INT UNSIGNED DEFAULT NULL,
  full_name       VARCHAR(150) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  phone           VARCHAR(30)  DEFAULT NULL,
  nationality     VARCHAR(100) DEFAULT NULL,
  adults          TINYINT UNSIGNED DEFAULT 1,
  children        TINYINT UNSIGNED DEFAULT 0,
  travel_date     DATE         DEFAULT NULL,
  special_request TEXT         DEFAULT NULL,
  status          ENUM('new','contacted','confirmed','cancelled') NOT NULL DEFAULT 'new',
  admin_notes     TEXT         DEFAULT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;



-- 11. Contact Inquiries
CREATE TABLE IF NOT EXISTS inquiries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(150) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  phone       VARCHAR(30)  DEFAULT NULL,
  subject     VARCHAR(250) DEFAULT NULL,
  message     TEXT         NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
