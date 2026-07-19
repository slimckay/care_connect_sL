-- Appointments / home visits — Care Connect SL
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  provider_id INT NOT NULL,
  referral_id INT NULL,
  visit_type ENUM('home_visit','clinic') NOT NULL DEFAULT 'home_visit',
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  patient_name VARCHAR(150) NOT NULL,
  patient_phone VARCHAR(40) NULL,
  address TEXT NULL,
  area VARCHAR(120) NULL,
  reason TEXT NULL,
  urgency ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  provider_notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_patient (patient_id),
  INDEX idx_provider (provider_id),
  INDEX idx_date (appointment_date),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
