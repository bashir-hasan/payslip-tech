<?php
declare(strict_types=1);

/**
 * Lightweight automatic database migrations.
 * Runs once per version bump; safe to run repeatedly (every step checks before altering).
 */
const DB_VERSION = 8;

function column_exists(string $table, string $column): bool
{
    return (bool)db_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

function run_migrations(): void
{
    $current = (int)(db_val("SELECT v FROM settings WHERE k = 'db_version'") ?? 1);
    if ($current >= DB_VERSION) return;

    if ($current < 2) {
        $add = [
            ['employees', 'custom', 'LONGTEXT NULL'],
            ['users', 'totp_secret', 'VARCHAR(255) NULL'],
            ['users', 'totp_recovery', 'TEXT NULL'],
            ['users', 'failed_logins', 'INT UNSIGNED NOT NULL DEFAULT 0'],
            ['users', 'locked_until', 'DATETIME NULL'],
            ['users', 'session_version', 'INT UNSIGNED NOT NULL DEFAULT 1'],
            ['activity', 'ip', 'VARCHAR(64) NULL'],
            ['verifications', 'country', 'CHAR(2) NULL'],
        ];
        foreach ($add as [$t, $c, $def]) {
            if (!column_exists($t, $c)) db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        }
    }
    if ($current < 3) {
        $add = [
            ['companies', 'country', "CHAR(2) NOT NULL DEFAULT 'BD'"],
            ['companies', 'currency', "CHAR(3) NOT NULL DEFAULT 'BDT'"],
            ['companies', 'labels', 'LONGTEXT NULL'],
            ['companies', 'plan', "VARCHAR(20) NOT NULL DEFAULT 'free'"],
            ['companies', 'plan_status', 'VARCHAR(20) NULL'],
            ['companies', 'plan_renews_at', 'DATETIME NULL'],
            ['companies', 'plan_cancel_at_end', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['companies', 'polar_customer_id', 'VARCHAR(64) NULL'],
            ['companies', 'polar_subscription_id', 'VARCHAR(64) NULL'],
        ];
        foreach ($add as [$t, $c, $def]) {
            if (!column_exists($t, $c)) db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS billing_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(100) NOT NULL,
            type VARCHAR(60) NOT NULL,
            company_id INT UNSIGNED NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_billing_event (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Default plans (editable in Admin → Billing & plans)
        if (!db_val("SELECT 1 FROM settings WHERE k = 'plans'")) {
            db_exec("INSERT INTO settings (k, v) VALUES ('plans', ?)", [json_encode(default_plans())]);
        }
    }
    if ($current < 4) {
        if (!column_exists('companies', 'automation')) db()->exec('ALTER TABLE `companies` ADD COLUMN `automation` LONGTEXT NULL');
        db()->exec("CREATE TABLE IF NOT EXISTS smart_usage (
            company_id INT UNSIGNED NOT NULL,
            ym CHAR(7) NOT NULL,
            used INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (company_id, ym)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS chat_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token CHAR(40) NOT NULL,
            user_id INT UNSIGNED NULL,
            visitor_name VARCHAR(120) NULL,
            visitor_email VARCHAR(190) NULL,
            page VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'bot',
            lang VARCHAR(5) NOT NULL DEFAULT 'en',
            ip VARCHAR(64) NULL,
            country CHAR(2) NULL,
            unread_admin INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_chat_token (token),
            KEY idx_chat_status (status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id INT UNSIGNED NOT NULL,
            sender VARCHAR(10) NOT NULL,
            body TEXT NOT NULL,
            kb_id INT UNSIGNED NULL,
            unanswered TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_msg_session (session_id, id),
            KEY idx_msg_unanswered (unanswered)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS kb_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question VARCHAR(255) NOT NULL,
            answer TEXT NOT NULL,
            keywords VARCHAR(500) NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'en',
            link VARCHAR(255) NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!(int)db_val('SELECT COUNT(*) FROM kb_entries')) seed_knowledge_base();
        foreach (['chat_enabled' => '1', 'chat_greeting' => "Hi! I'm the PaySlip.tech assistant. Ask me anything about payroll, payslips, verification or pricing."] as $k => $v) {
            if (db_val('SELECT 1 FROM settings WHERE k = ?', [$k]) === null) db_exec('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
        }
    }
    if ($current < 5) {
        db()->exec("CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(160) NOT NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'en',
            title VARCHAR(200) NOT NULL,
            excerpt VARCHAR(400) NULL,
            body LONGTEXT NOT NULL,
            faq LONGTEXT NULL,
            cover VARCHAR(255) NULL,
            meta_title VARCHAR(200) NULL,
            meta_description VARCHAR(300) NULL,
            keyword VARCHAR(120) NULL,
            category VARCHAR(80) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'draft',
            author_id INT UNSIGNED NULL,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_post_slug (slug),
            KEY idx_post_pub (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!(int)db_val('SELECT COUNT(*) FROM posts')) seed_blog_posts();
    }
    if ($current < 6) {
        // Paid plans are live: drop "beta" wording from stored chatbot answers
        db_exec("UPDATE kb_entries SET answer = REPLACE(answer, 'It is free during the beta.', 'It is free and needs no key.') WHERE answer LIKE '%during the beta%'");
    }
    if ($current < 7) {
        // v1.5: team roles, payroll approval, loans, leave, verified documents, proof packs
        $T = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if (!column_exists('users', 'company_role')) db()->exec("ALTER TABLE users ADD company_role VARCHAR(12) NULL AFTER company_id");
        db_exec("UPDATE users SET company_role = 'owner' WHERE role = 'employer' AND company_role IS NULL");
        if (!column_exists('payroll_runs', 'submitted_at')) db()->exec("ALTER TABLE payroll_runs ADD submitted_at DATETIME NULL, ADD submitted_by INT UNSIGNED NULL, ADD approved_by INT UNSIGNED NULL");
        if (!column_exists('companies', 'weekend')) db()->exec("ALTER TABLE companies ADD weekend VARCHAR(20) NULL");
        if (!column_exists('companies', 'domain')) db()->exec("ALTER TABLE companies ADD domain VARCHAR(190) NULL, ADD domain_verified_at DATETIME NULL, ADD business_verified_at DATETIME NULL");
        if (!column_exists('verifications', 'document_id')) db()->exec("ALTER TABLE verifications ADD document_id INT UNSIGNED NULL AFTER payslip_id");
        db()->exec("CREATE TABLE IF NOT EXISTS loans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            principal DECIMAL(14,2) NOT NULL,
            installment DECIMAL(14,2) NOT NULL,
            start_period CHAR(7) NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            KEY idx_loan_emp (company_id, employee_id, status)
        )$T");
        db()->exec("CREATE TABLE IF NOT EXISTS loan_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            loan_id INT UNSIGNED NOT NULL,
            payslip_id INT UNSIGNED NOT NULL,
            period CHAR(7) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_loan_slip (loan_id, payslip_id)
        )$T");
        db()->exec("CREATE TABLE IF NOT EXISTS leave_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            name VARCHAR(60) NOT NULL,
            days_per_year DECIMAL(5,1) NOT NULL DEFAULT 0,
            paid TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            KEY idx_lt_co (company_id)
        )$T");
        db()->exec("CREATE TABLE IF NOT EXISTS leave_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            type_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days DECIMAL(5,1) NOT NULL,
            reason VARCHAR(300) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            requested_by INT UNSIGNED NULL,
            decided_by INT UNSIGNED NULL,
            decided_at DATETIME NULL,
            decision_note VARCHAR(200) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_lr_co (company_id, status),
            KEY idx_lr_emp (employee_id, start_date)
        )$T");
        db()->exec("CREATE TABLE IF NOT EXISTS documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            code VARCHAR(20) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'requested',
            addressed_to VARCHAR(200) NULL,
            purpose VARCHAR(200) NULL,
            year SMALLINT UNSIGNED NULL,
            snapshot LONGTEXT NULL,
            hash CHAR(64) NULL,
            requested_by INT UNSIGNED NULL,
            issued_by INT UNSIGNED NULL,
            issued_at DATETIME NULL,
            revoked_at DATETIME NULL,
            revoke_reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_doc_code (code),
            KEY idx_doc_co (company_id, status),
            KEY idx_doc_emp (employee_id)
        )$T");
        db()->exec("CREATE TABLE IF NOT EXISTS shares (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_enc VARCHAR(255) NOT NULL,
            label VARCHAR(120) NULL,
            items LONGTEXT NOT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            last_viewed_at DATETIME NULL,
            UNIQUE KEY uq_share_tok (token_hash),
            KEY idx_share_emp (employee_id)
        )$T");
        $kb = [
            ['Can I get a salary certificate or employment letter?', 'Yes. Employees request an employment letter or salary certificate from My documents, and the employer issues it in one click. Each one has a QR code and a DC- code that banks and embassies can verify, just like a payslip. Employees can also create a yearly income statement instantly.', 'salary certificate, employment letter, experience, noc, visa letter, income statement, document', '/verify'],
            ['How do I share proof of income with a bank or embassy?', 'Open Proof of income in the employee portal, choose the last 3, 6 or 12 months and how long the link should work, then send the link. The bank sees every payslip checked live. You can show full amounts, only confirm that net pay is above an amount, or hide amounts. You can switch the link off at any time.', 'proof of income, bank statement, loan, share payslips, link, landlord, embassy', '/verify'],
            ['Can HR and the accountant have their own logins?', 'Yes. On Starter and Business plans the owner can invite an HR manager and an accountant from Team. HR prepares payroll and submits it, the accountant approves and issues the payslips. Each person has their own sign-in.', 'team, roles, hr, accountant, approval, users, multiple logins', '/#pricing'],
            ['Does PaySlip handle leave and salary advances?', 'Yes. Employees request leave from their portal and HR approves it. Approved unpaid leave is deducted in that month automatically. Salary advances and loans are added once with a monthly instalment that PaySlip deducts until it is repaid.', 'leave, attendance, holiday, absence, advance, loan, instalment, kisti', '/#features'],
            ['How do I know the employer on a payslip is real?', 'The verification page shows an About this employer panel: whether the company proved it owns its web domain, whether PaySlip.tech checked its business documents, and how many months of payroll it has issued. New employers are clearly marked so you can take extra care.', 'fake company, real employer, trust, verified employer, domain, scam', '/verify'],
        ];
        foreach ($kb as [$q, $a, $k, $l]) {
            if (!db_val('SELECT 1 FROM kb_entries WHERE question = ?', [$q])) db_insert('kb_entries', ['question' => $q, 'answer' => $a, 'keywords' => $k, 'lang' => 'en', 'link' => $l, 'hits' => 0, 'active' => 1, 'created_at' => now()]);
        }
    }
    if ($current < 8) {
        // v1.6: increments, company seal & signature, reminders
        $T = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if (!column_exists('companies', 'signature_img')) db()->exec("ALTER TABLE companies ADD signature_img VARCHAR(120) NULL, ADD stamp_img VARCHAR(120) NULL");
        db()->exec("CREATE TABLE IF NOT EXISTS salary_changes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            effective_period CHAR(7) NOT NULL,
            old_salary TEXT NOT NULL,
            new_salary TEXT NOT NULL,
            new_designation VARCHAR(190) NULL,
            reason VARCHAR(255) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'applied',
            document_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            applied_at DATETIME NULL,
            KEY idx_sc_emp (employee_id, effective_period),
            KEY idx_sc_pending (company_id, status)
        )$T");
    }
    db_exec("REPLACE INTO settings (k, v) VALUES ('db_version', ?)", [(string)DB_VERSION]);
}
