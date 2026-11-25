<?php
declare(strict_types=1);

/**
 * Lightweight automatic database migrations.
 * Runs once per version bump; safe to run repeatedly (every step checks before altering).
 */
const DB_VERSION = 6;

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
    db_exec("REPLACE INTO settings (k, v) VALUES ('db_version', ?)", [(string)DB_VERSION]);
}
