CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  logo VARCHAR(190) NULL,
  address TEXT NULL,
  phone VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  website VARCHAR(190) NULL,
  tin VARCHAR(60) NULL,
  signatory_name VARCHAR(190) NULL,
  signatory_title VARCHAR(190) NULL,
  payslip_lang VARCHAR(5) NOT NULL DEFAULT 'en',
  show_amounts TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(20) NOT NULL,
  company_id INT UNSIGNED NULL,
  employee_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  token VARCHAR(80) NULL,
  token_expires DATETIME NULL,
  lang VARCHAR(5) NOT NULL DEFAULT 'en',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_company (company_id),
  KEY idx_users_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  emp_code VARCHAR(60) NOT NULL,
  name VARCHAR(190) NOT NULL,
  designation VARCHAR(190) NULL,
  department VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  join_date DATE NULL,
  payment_method VARCHAR(20) NOT NULL DEFAULT 'bank',
  bank_name VARCHAR(190) NULL,
  account_no VARCHAR(80) NULL,
  basic DECIMAL(12,2) NOT NULL DEFAULT 0,
  house_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
  medical DECIMAL(12,2) NOT NULL DEFAULT 0,
  conveyance DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  pf DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_emp_code (company_id, emp_code),
  KEY idx_emp_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  pay_date DATE NULL,
  working_days TINYINT UNSIGNED NOT NULL DEFAULT 30,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NULL,
  finalized_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_run_period (company_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payslips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id INT UNSIGNED NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  snapshot LONGTEXT NULL,
  adjustments LONGTEXT NULL,
  breakdown LONGTEXT NULL,
  gross DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net DECIMAL(12,2) NOT NULL DEFAULT 0,
  hash CHAR(64) NULL,
  issued_at DATETIME NULL,
  revoked_at DATETIME NULL,
  revoke_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_payslip_code (code),
  UNIQUE KEY uq_payslip_run_emp (run_id, employee_id),
  KEY idx_payslip_company (company_id),
  KEY idx_payslip_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payslip_id INT UNSIGNED NULL,
  company_id INT UNSIGNED NULL,
  code_queried VARCHAR(40) NOT NULL,
  result VARCHAR(20) NOT NULL,
  verifier_type VARCHAR(30) NULL,
  channel VARCHAR(10) NOT NULL DEFAULT 'web',
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ver_company (company_id),
  KEY idx_ver_payslip (payslip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k VARCHAR(120) PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(80) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_act_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
