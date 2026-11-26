<?php
$cu = current_user();
$nav = $nav ?? '';
$co = null;
if ($cu && $cu['company_id']) $co = db_one('SELECT id, name, logo FROM companies WHERE id = ?', [$cu['company_id']]);
$link = function (string $href, string $key, string $ic, string $label, $count = null) use ($nav) {
    $on = $nav === $key ? ' class="on" aria-current="page"' : '';
    $c = $count !== null ? '<span class="count">' . e((string)$count) . '</span>' : '';
    return '<a href="' . e(url($href)) . '"' . $on . '>' . icon($ic) . '<span>' . e($label) . '</span>' . $c . '</a>';
};
$isDemo = $co && (int)$co['id'] === demo_cid();
$appCode = setting('code_in_app', '0') === '1' && tracking_allowed();
send_security_headers($appCode);
?><!doctype html>
<html lang="<?= e(lang()) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', get_defined_vars() + ['noindex' => true, 'context' => 'app']); ?></head>
<body>
<a class="skip-link" href="#main"><?= e(t('Skip to main content')) ?></a>
<?php if ($appCode) echo body_tracking_code(); ?>
<?php partial('flash'); ?>
<div class="shell">
  <aside class="sidebar">
    <?php partial('brand'); ?>
    <?php if ($co): ?>
      <div class="side-co">
        <div class="logo"><?php if ($co['logo']): ?><img src="<?= e(url('uploads/logos/' . $co['logo'])) ?>" alt=""><?php else: ?><?= e(initials($co['name'])) ?><?php endif; ?></div>
        <div><b title="<?= e($co['name']) ?>"><?= e($co['name']) ?></b><span><?= e($cu['role'] === 'employee' ? t('Employee portal') : (company_roles()[company_role($cu)] ?? t('Employer'))) ?></span></div>
      </div>
    <?php endif; ?>
    <?php
    // Groups fold away so the whole menu fits a laptop screen; the group holding the current page starts open
    $group = function (string $id, string $label, array $keys, string $html) use ($nav) {
        if (trim($html) === '') return '';
        $open = in_array($nav, $keys, true) ? ' open data-current' : '';
        return '<details class="nav-group" data-group="' . e($id) . '"' . $open . '><summary class="nav-label">' . e($label) . icon('chevron-down', 14) . '</summary>' . $html . '</details>';
    };
    $planPill = '';
    if ($cu['role'] === 'employer' && $co && company_role($cu) === 'owner' && billing_enabled()) {
        $fullCo = db_one('SELECT * FROM companies WHERE id = ?', [$co['id']]);
        $cp = company_plan($fullCo);
        $planPill = (int)$co['id'] === demo_cid()
            ? '<a class="plan-pill free" href="' . e(url('/app/billing')) . '">' . icon('zap', 15) . '<span><b>' . e(t('Plans & pricing')) . '</b>' . e(t('Free, Starter and Business')) . '</span>' . icon('arrow-right', 15) . '</a>'
            : ($cp['key'] === 'free'
            ? '<a class="plan-pill free" href="' . e(url('/app/billing')) . '">' . icon('zap', 15) . '<span><b>' . e(t('Free plan')) . '</b>' . e(t('See plans & upgrade')) . '</span>' . icon('arrow-right', 15) . '</a>'
            : '<a class="plan-pill" href="' . e(url('/app/billing')) . '">' . icon('zap', 15) . '<span><b>' . e(sprintf(t('%s plan'), $cp['name'])) . '</b>' . e(t('Manage billing')) . '</span>' . icon('arrow-right', 15) . '</a>');
    }
    ?>
    <nav class="nav" aria-label="<?= e(t('Main')) ?>">
      <?php if ($cu['role'] === 'employer'): ?>
        <?= $link('/app', 'dashboard', 'dashboard', t('Dashboard')) ?>
        <?= $link('/app/employees', 'employees', 'users', t('Employees')) ?>
        <?= $link('/app/payroll', 'payroll', 'wallet', t('Payroll')) ?>
        <?= $link('/app/payslips', 'payslips', 'file', t('Payslips')) ?>
        <?= $link('/app/verifications', 'verifications', 'shield', t('Verification log')) ?>
        <?= $link('/app/reports', 'reports', 'chart', t('Reports')) ?>
        <?php $lp = (int)db_val("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'", [$co['id']]); $dr = (int)db_val("SELECT COUNT(*) FROM documents WHERE company_id = ? AND status = 'requested'", [$co['id']]); ?>
        <?= $group('people', t('People') . (($lp + $dr) ? ' · ' . ($lp + $dr) : ''), ['leave', 'loans', 'documents'],
            $link('/app/leave', 'leave', 'calendar', t('Leave & attendance'), $lp ?: null)
            . $link('/app/loans', 'loans', 'bank', t('Loans & advances'))
            . $link('/app/documents', 'documents', 'award', t('Documents'), $dr ?: null)) ?>
        <?= $group('automation', t('Automation'), ['assistant', 'automation', 'import'],
            $link('/app/assistant', 'assistant', 'zap', t('Smart assistant'))
            . (company_role($cu) === 'owner' ? $link('/app/automation', 'automation', 'clock', t('Auto payroll')) : '')
            . $link('/app/employees/import', 'import', 'upload', t('Smart import'))) ?>
        <?php if (company_role($cu) === 'owner'): ?>
        <?= $group('company', t('Company'), ['settings', 'team', 'billing'],
            $link('/app/settings', 'settings', 'settings', t('Settings'))
            . $link('/app/team', 'team', 'users', t('Team'))
            . (billing_enabled() ? $link('/app/billing', 'billing', 'wallet', t('Plan & billing')) : '')) ?>
        <?php endif; ?>
      <?php elseif ($cu['role'] === 'employee'): ?>
        <?= $link('/me', 'me', 'file', t('My payslips')) ?>
        <?= $link('/me/proof', 'me-proof', 'send', t('Proof of income')) ?>
        <?= $link('/me/documents', 'me-docs', 'award', t('My documents')) ?>
        <?= $link('/me/leave', 'me-leave', 'calendar', t('Leave & advances')) ?>
      <?php elseif ($cu['role'] === 'admin'): ?>
        <?= $link('/admin', 'admin', 'chart', t('Overview')) ?>
        <?= $link('/admin/companies', 'admin-companies', 'building', t('Companies')) ?>
        <?= $link('/admin/users', 'admin-users', 'users', t('Users')) ?>
        <?= $link('/admin/verifications', 'admin-verifications', 'shield', t('Verifications')) ?>
        <?php $waiting = (int)db_val("SELECT COUNT(*) FROM chat_sessions WHERE status = 'human' AND unread_admin > 0"); ?>
        <?= $link('/admin/chat', 'admin-chat', 'chat', t('Live chat'), $waiting ?: null) ?>
        <?= $link('/admin/kb', 'admin-kb', 'edit', t('Train chatbot')) ?>
        <?= $group('site', t('Site'), ['admin-blog', 'admin-seo', 'admin-code', 'admin-fields', 'admin-security', 'admin-billing', 'admin-settings'],
            $link('/admin/blog', 'admin-blog', 'file', t('Blog'))
            . $link('/admin/seo', 'admin-seo', 'globe', t('SEO & AI search'))
            . $link('/admin/code', 'admin-code', 'code', t('Code & tracking'))
            . $link('/admin/fields', 'admin-fields', 'edit', t('Custom fields'))
            . $link('/admin/security', 'admin-security', 'lock', t('Security'))
            . $link('/admin/billing', 'admin-billing', 'wallet', t('Billing & plans'))
            . $link('/admin/settings', 'admin-settings', 'settings', t('Platform settings'))) ?>
      <?php endif; ?>
      <?= $group('tools', t('Tools'), ['verify', 'account'],
          $link('/verify', 'verify', 'qr', t('Verify a payslip'))
          . $link('/account', 'account', 'user', t('My account'))) ?>
    </nav>
    <div class="side-foot">
      <?= $planPill ?>
      <div class="side-user">
        <a class="avatar" href="<?= e(url('/account')) ?>" title="<?= e(t('My account')) ?>" aria-label="<?= e(t('My account')) ?>"><?= e(initials($cu['name'])) ?></a>
        <div style="min-width:0;flex:1"><b><?= e($cu['name']) ?></b><span><?= e($cu['email']) ?></span></div>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon btn-sm" title="<?= e(t('Sign out')) ?>" aria-label="<?= e(t('Sign out')) ?>"><?= icon('logout') ?></button></form>
      </div>
    </div>
  </aside>
  <div class="backdrop"></div>
  <div class="main">
    <?php if ($isDemo): ?><div class="announce"><?= e(t('Demo mode: this is a sample company with fictional employees. Changes are visible to other visitors.')) ?> <a href="<?= e(url('/register')) ?>" style="color:var(--lime);font-weight:600"><?= e(t('Create your own account')) ?> →</a></div><?php endif; ?>
    <header class="appbar">
      <div class="row" style="gap:10px;min-width:0">
        <button class="btn btn-ghost btn-icon menu-toggle" data-nav-toggle aria-label="<?= e(t('Menu')) ?>" aria-expanded="false"><?= icon('menu', 22) ?></button>
        <div class="crumbs"><b><?= e($title ?? '') ?></b></div>
      </div>
      <div class="row" style="gap:8px">
        <div class="appbar-actions" data-appbar-actions aria-hidden="true"></div>
        <?php partial('themetoggle'); ?>
        <?php partial('langswitch'); ?>
      </div>
    </header>
    <main class="content" id="main" tabindex="-1"><?= $content ?></main>
    <button type="button" class="to-top" data-to-top hidden aria-label="<?= e(t('Back to top')) ?>" title="<?= e(t('Back to top')) ?>"><?= icon('arrow-up', 20) ?></button>
  </div>
</div>
<?php if (setting('code_in_app', '0') === '1') partial('consent'); ?>
<?php if (setting('chat_in_app', '0') === '1' && $cu['role'] !== 'admin') partial('chatwidget'); ?>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if ($appCode) echo footer_tracking_code(); ?>
</body>
</html>
