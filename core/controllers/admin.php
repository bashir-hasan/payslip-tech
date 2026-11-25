<?php
declare(strict_types=1);

function admin_dashboard(): void
{
    require_role('admin');
    $since = date('Y-m-d H:i:s', strtotime('-30 days'));
    $d = demo_cid(); // real usage numbers exclude the demo company
    $stats = [
        'companies' => (int)db_val('SELECT COUNT(*) FROM companies WHERE id <> ?', [$d]),
        'companies30' => (int)db_val('SELECT COUNT(*) FROM companies WHERE created_at >= ? AND id <> ?', [$since, $d]),
        'employees' => (int)db_val("SELECT COUNT(*) FROM employees WHERE status = 'active' AND company_id <> ?", [$d]),
        'payslips' => (int)db_val("SELECT COUNT(*) FROM payslips WHERE status IN ('issued','revoked') AND company_id <> ?", [$d]),
        'countries' => (int)db_val('SELECT COUNT(DISTINCT country) FROM companies WHERE id <> ?', [$d]),
        'paid' => (int)db_val("SELECT COUNT(*) FROM companies WHERE id <> ? AND plan <> 'free' AND plan_status IN ('active','trialing')", [$d]),
        'verifications' => (int)db_val('SELECT COUNT(*) FROM verifications WHERE COALESCE(company_id,0) <> ?', [$d]),
        'verifications30' => (int)db_val('SELECT COUNT(*) FROM verifications WHERE created_at >= ? AND COALESCE(company_id,0) <> ?', [$since, $d]),
        'api_calls' => (int)db_val("SELECT COUNT(*) FROM verifications WHERE channel = 'api' AND COALESCE(company_id,0) <> ?", [$d]),
        'users' => (int)db_val('SELECT COUNT(*) FROM users WHERE COALESCE(company_id,0) <> ?', [$d]),
    ];
    $results = db_all('SELECT result, COUNT(*) AS n FROM verifications WHERE COALESCE(company_id,0) <> ? GROUP BY result', [$d]);
    $daily = db_all('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM verifications WHERE created_at >= ? AND COALESCE(company_id,0) <> ? GROUP BY DATE(created_at) ORDER BY d', [date('Y-m-d 00:00:00', strtotime('-13 days')), $d]);
    $companies = db_all('SELECT c.*, (SELECT COUNT(*) FROM employees e WHERE e.company_id = c.id) AS emps FROM companies c ORDER BY c.id DESC LIMIT 6');
    $activity = db_all('SELECT a.*, c.name AS company FROM activity a LEFT JOIN companies c ON c.id = a.company_id ORDER BY a.id DESC LIMIT 10');
    render('admin/dashboard', ['title' => t('Platform overview'), 'nav' => 'admin', 'stats' => $stats, 'results' => array_column($results, 'n', 'result'), 'daily' => $daily, 'companies' => $companies, 'activity' => $activity], 'app');
}

function admin_companies(): void
{
    require_role('admin');
    $q = (string)input('q');
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM employees e WHERE e.company_id = c.id AND e.status='active') AS emps, (SELECT COUNT(*) FROM payslips p WHERE p.company_id = c.id AND p.status IN ('issued','revoked')) AS slips, (SELECT COUNT(*) FROM verifications v WHERE v.company_id = c.id) AS checks, (SELECT u.email FROM users u WHERE u.company_id = c.id AND u.role='employer' ORDER BY u.id LIMIT 1) AS owner_email FROM companies c";
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE c.name LIKE ? OR c.email LIKE ?';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }
    $rows = db_all($sql . ' ORDER BY c.id DESC LIMIT 500', $params);
    render('admin/companies', ['title' => t('Companies'), 'nav' => 'admin-companies', 'rows' => $rows, 'q' => $q], 'app');
}

function admin_company_status(string $id): void
{
    require_role('admin');
    $c = db_one('SELECT * FROM companies WHERE id = ?', [(int)$id]);
    if (!$c) abort(404);
    $new = $c['status'] === 'active' ? 'suspended' : 'active';
    db_update('companies', ['status' => $new], 'id = ?', [$c['id']]);
    log_activity((int)$c['id'], ($new === 'active' ? 'Reactivated' : 'Suspended') . ' by platform admin');
    flash('success', $new === 'active' ? t('Company reactivated.') : t('Company suspended. Its users can no longer sign in. Issued payslips still verify.'));
    back('/admin/companies');
}

function admin_users(): void
{
    require_role('admin');
    $q = (string)input('q');
    $sql = 'SELECT u.*, c.name AS company FROM users u LEFT JOIN companies c ON c.id = u.company_id';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }
    $rows = db_all($sql . ' ORDER BY u.id DESC LIMIT 500', $params);
    render('admin/users', ['title' => t('Users'), 'nav' => 'admin-users', 'rows' => $rows, 'q' => $q], 'app');
}

function admin_user_status(string $id): void
{
    $me = require_role('admin');
    $u = db_one('SELECT * FROM users WHERE id = ?', [(int)$id]);
    if (!$u || (int)$u['id'] === (int)$me['id']) {
        flash('error', t('You cannot change your own account here.'));
        back('/admin/users');
    }
    if ($u['status'] === 'invited') back('/admin/users');
    $new = $u['status'] === 'active' ? 'suspended' : 'active';
    db_update('users', ['status' => $new], 'id = ?', [$u['id']]);
    flash('success', $new === 'active' ? t('User reactivated.') : t('User suspended.'));
    back('/admin/users');
}

function admin_verifications(): void
{
    require_role('admin');
    $rows = db_all('SELECT v.*, c.name AS company, p.snapshot FROM verifications v LEFT JOIN companies c ON c.id = v.company_id LEFT JOIN payslips p ON p.id = v.payslip_id ORDER BY v.id DESC LIMIT 500');
    render('admin/verifications', ['title' => t('All verifications'), 'nav' => 'admin-verifications', 'rows' => $rows], 'app');
}

function admin_settings(): void
{
    require_role('admin');
    render('admin/settings', ['title' => t('Platform settings'), 'nav' => 'admin-settings'], 'app');
}

function admin_settings_save(): void
{
    require_role('admin');
    set_setting('site_name', mb_substr((string)input('site_name'), 0, 80) ?: 'PaySlip.tech');
    $se = (string)input('support_email');
    set_setting('support_email', filter_var($se, FILTER_VALIDATE_EMAIL) ? $se : null);
    $mf = (string)input('mail_from');
    set_setting('mail_from', filter_var($mf, FILTER_VALIDATE_EMAIL) ? $mf : null);
    set_setting('allow_registration', input('allow_registration') ? '1' : '0');
    set_setting('demo_enabled', input('demo_enabled') ? '1' : '0');
    set_setting('announcement', mb_substr((string)input('announcement'), 0, 200) ?: null);
    // Email
    set_setting('mail_driver', input('mail_driver') === 'smtp' ? 'smtp' : 'mail');
    set_setting('smtp_host', mb_substr(trim((string)input('smtp_host')), 0, 190) ?: null);
    set_setting('smtp_port', (string)max(1, min(65535, (int)input('smtp_port', '465'))));
    set_setting('smtp_secure', in_array(input('smtp_secure'), ['ssl', 'tls', 'none'], true) ? (string)input('smtp_secure') : 'ssl');
    set_setting('smtp_user', mb_substr(trim((string)input('smtp_user')), 0, 190) ?: null);
    $sp = (string)($_POST['smtp_pass'] ?? '');
    if ($sp !== '') set_setting('smtp_pass', encrypt_str($sp));
    audit('Updated platform settings');
    flash('success', t('Settings saved.'));
    if (input('send_test')) {
        $to = (string)(current_user()['email'] ?? '');
        if (send_mail($to, t('Test email') . ' — ' . site_name(), t('Email sending works. Invitations, password resets and payslips will be delivered from this address.'))) {
            flash('success', sprintf(t('Test email sent to %s. Check the inbox and spam folder.'), $to));
        } else {
            flash('error', t('Test email failed:') . ' ' . mail_last_error());
        }
    }
    redirect('/admin/settings');
}

/* ---------------- SEO settings ---------------- */

function admin_seo(): void
{
    require_role('admin');
    render('admin/seo', ['title' => t('SEO & AI search'), 'nav' => 'admin-seo'], 'app');
}

function admin_seo_save(): void
{
    require_role('admin');
    $txt = fn($k, $max = 300) => mb_substr(trim(strip_tags((string)input($k))), 0, $max) ?: null;
    foreach (['seo_default_description', 'seo_default_description_bn'] as $k) set_setting($k, $txt($k, 300));
    foreach (array_keys(SEO_PAGES) as $page) {
        foreach (['title' => 90, 'description' => 300] as $f => $max) {
            set_setting("seo_{$page}_{$f}", $txt("seo_{$page}_{$f}", $max));
            set_setting("seo_{$page}_{$f}_bn", $txt("seo_{$page}_{$f}_bn", $max));
        }
    }
    set_setting('seo_twitter', ltrim((string)$txt('seo_twitter', 40), '@') ?: null);
    set_setting('seo_founder', $txt('seo_founder', 120));
    $same = array_filter(array_map('trim', preg_split('/\r?\n/', (string)input('seo_same_as')) ?: []), fn($l) => filter_var($l, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $l));
    set_setting('seo_same_as', implode("\n", array_slice($same, 0, 12)) ?: null);
    set_setting('llms_extra', mb_substr(trim(strip_tags((string)input('llms_extra'))), 0, 4000) ?: null);
    set_setting('seo_noindex', input('seo_noindex') ? '1' : '0');

    if (!empty($_FILES['og_image']['name'])) {
        $f = $_FILES['og_image'];
        $info = $f['error'] === UPLOAD_ERR_OK && $f['size'] <= 2 * 1024 * 1024 ? @getimagesize($f['tmp_name']) : false;
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            flash('error', t('Social image must be a PNG or JPG under 2 MB (1200 × 630 recommended).'));
            redirect('/admin/seo');
        }
        $name = 'og-' . bin2hex(random_bytes(6)) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
        $dest = ROOT . '/uploads/logos/' . $name;
        $ok = function_exists('imagecreatefromstring')
            ? (function () use ($f, $dest, $info) {
                $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
                if (!$img) return false;
                $r = $info[2] === IMAGETYPE_PNG ? imagepng($img, $dest) : imagejpeg($img, $dest, 90);
                imagedestroy($img);
                return $r;
            })()
            : move_uploaded_file($f['tmp_name'], $dest);
        if ($ok) {
            if ($old = setting('seo_og_image')) @unlink(ROOT . '/uploads/logos/' . basename($old));
            set_setting('seo_og_image', $name);
        }
    } elseif (input('remove_og')) {
        if ($old = setting('seo_og_image')) @unlink(ROOT . '/uploads/logos/' . basename($old));
        set_setting('seo_og_image', null);
    }
    audit('Updated SEO settings');
    flash('success', t('SEO settings saved.'));
    redirect('/admin/seo');
}

/* ---------------- Code & tracking ---------------- */

function admin_code(): void
{
    require_role('admin');
    render('admin/code', ['title' => t('Code & tracking'), 'nav' => 'admin-code'], 'app');
}

function admin_code_save(): void
{
    require_role('admin');
    $id = fn($k, $re) => preg_match($re, trim((string)input($k))) ? trim((string)input($k)) : null;
    set_setting('ga4_id', $id('ga4_id', '/^G-[A-Z0-9]{4,20}$/i'));
    set_setting('gtm_id', $id('gtm_id', '/^GTM-[A-Z0-9]{4,12}$/i'));
    set_setting('fb_pixel_id', $id('fb_pixel_id', '/^\d{6,20}$/'));
    set_setting('clarity_id', $id('clarity_id', '/^[a-z0-9]{6,20}$/i'));
    set_setting('google_site_verification', $id('google_site_verification', '/^[A-Za-z0-9_-]{10,100}$/'));
    set_setting('bing_site_verification', $id('bing_site_verification', '/^[A-Za-z0-9]{10,64}$/'));
    set_setting('fb_domain_verification', $id('fb_domain_verification', '/^[a-z0-9]{10,64}$/i'));
    foreach (['code_head', 'code_body', 'code_footer'] as $k) {
        $v = (string)($_POST[$k] ?? '');
        set_setting($k, trim($v) === '' ? null : mb_substr($v, 0, 20000));
    }
    set_setting('code_in_app', input('code_in_app') ? '1' : '0');
    foreach (['ga4_id' => 'Google Analytics', 'gtm_id' => 'Google Tag Manager', 'fb_pixel_id' => 'Meta Pixel', 'clarity_id' => 'Microsoft Clarity'] as $k => $label) {
        if (trim((string)input($k)) !== '' && !setting($k)) flash('error', sprintf(t('%s ID was not saved because its format looks wrong.'), $label));
    }
    audit('Updated tracking / custom code');
    flash('success', t('Code settings saved.'));
    redirect('/admin/code');
}

/* ---------------- Custom fields ---------------- */

function admin_fields(): void
{
    require_role('admin');
    render('admin/fields', ['title' => t('Custom fields'), 'nav' => 'admin-fields', 'fields' => custom_fields()], 'app');
}

function admin_fields_save(): void
{
    require_role('admin');
    $fields = custom_fields();
    $action = (string)input('action');
    if ($action === 'add') {
        $label = mb_substr(trim(strip_tags((string)input('label'))), 0, 60);
        $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string)(input('key') ?: $label)), '_'));
        $key = substr($key, 0, 40);
        $type = in_array(input('type'), ['text', 'number', 'date', 'select'], true) ? (string)input('type') : 'text';
        $reserved = array_merge(['name', 'basic', 'email', 'phone', 'emp_code', 'designation', 'department', 'id', 'status'], array_keys(EARNING_KEYS), array_keys(DEDUCTION_KEYS));
        if ($label === '' || $key === '' || in_array($key, $reserved, true) || in_array($key, array_column($fields, 'key'), true)) {
            flash('error', t('Enter a unique field name.'));
            redirect('/admin/fields');
        }
        if (count($fields) >= 20) {
            flash('error', t('You can add up to 20 custom fields.'));
            redirect('/admin/fields');
        }
        $fields[] = [
            'key' => $key,
            'label' => $label,
            'label_bn' => mb_substr(trim(strip_tags((string)input('label_bn'))), 0, 60),
            'type' => $type,
            'options' => $type === 'select' ? mb_substr(trim(strip_tags((string)input('options'))), 0, 500) : '',
            'on_payslip' => (bool)input('on_payslip'),
            'required' => (bool)input('required'),
        ];
        flash('success', t('Field added. It now appears on every employee form.'));
    } elseif ($action === 'delete') {
        $k = (string)input('key');
        $fields = array_values(array_filter($fields, fn($f) => $f['key'] !== $k));
        flash('success', t('Field removed. Values already saved on employees are kept but hidden.'));
    } elseif ($action === 'toggle') {
        $k = (string)input('key');
        $prop = input('prop') === 'required' ? 'required' : 'on_payslip';
        foreach ($fields as &$f) if ($f['key'] === $k) $f[$prop] = !$f[$prop];
        unset($f);
    } elseif ($action === 'move') {
        $k = (string)input('key');
        $dir = input('dir') === 'up' ? -1 : 1;
        $i = array_search($k, array_column($fields, 'key'), true);
        if ($i !== false && isset($fields[$i + $dir])) [$fields[$i], $fields[$i + $dir]] = [$fields[$i + $dir], $fields[$i]];
    }
    set_setting('custom_fields', json_encode(array_values($fields), JSON_UNESCAPED_UNICODE));
    audit('Updated custom fields');
    redirect('/admin/fields');
}

function admin_security(): void
{
    require_role('admin');
    $events = db_all("SELECT a.*, u.email FROM activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.action LIKE 'Failed%' OR a.action LIKE 'Sign%' OR a.action LIKE 'Password%' OR a.action LIKE 'Two-factor%' OR a.action LIKE 'Updated%' ORDER BY a.id DESC LIMIT 200");
    $locked = db_all('SELECT id, name, email, locked_until FROM users WHERE locked_until > ?', [now()]);
    $no2fa = db_all("SELECT id, name, email FROM users WHERE role = 'admin' AND (totp_secret IS NULL OR totp_secret = '')");
    render('admin/security', ['title' => t('Security'), 'nav' => 'admin-security', 'events' => $events, 'locked' => $locked, 'no2fa' => $no2fa], 'app');
}

function admin_unlock(string $id): void
{
    require_role('admin');
    db_update('users', ['locked_until' => null, 'failed_logins' => 0], 'id = ?', [(int)$id]);
    audit('Unlocked user #' . (int)$id);
    flash('success', t('Account unlocked.'));
    redirect('/admin/security');
}
