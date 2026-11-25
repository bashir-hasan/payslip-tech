<?php
$cu = current_user();
$nav = $nav ?? '';
$co = null;
if ($cu && $cu['company_id']) $co = db_one('SELECT id, name, logo FROM companies WHERE id = ?', [$cu['company_id']]);
$link = function (string $href, string $key, string $ic, string $label, $count = null) use ($nav) {
    $on = $nav === $key ? ' class="on"' : '';
    $c = $count !== null ? '<span class="count">' . e((string)$count) . '</span>' : '';
    return '<a href="' . e(url($href)) . '"' . $on . '>' . icon($ic) . '<span>' . e($label) . '</span>' . $c . '</a>';
};
$isDemo = $co && (int)$co['id'] === demo_cid();
$appCode = setting('code_in_app', '0') === '1' && has_custom_scripts();
send_security_headers($appCode);
?><!doctype html>
<html lang="<?= e(lang()) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', get_defined_vars() + ['noindex' => true, 'context' => 'app']); ?></head>
<body>
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
    <nav class="nav">
      <?php if ($cu['role'] === 'employer'): ?>
        <?= $link('/app', 'dashboard', 'dashboard', t('Dashboard')) ?>
        <?= $link('/app/employees', 'employees', 'users', t('Employees')) ?>
        <?= $link('/app/payroll', 'payroll', 'wallet', t('Payroll')) ?>
        <?= $link('/app/payslips', 'payslips', 'file', t('Payslips')) ?>
        <?= $link('/app/verifications', 'verifications', 'shield', t('Verification log')) ?>
        <div class="nav-label"><?= e(t('People')) ?></div>
        <?php $lp = (int)db_val("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'", [$co['id']]); $dr = (int)db_val("SELECT COUNT(*) FROM documents WHERE company_id = ? AND status = 'requested'", [$co['id']]); ?>
        <?= $link('/app/leave', 'leave', 'calendar', t('Leave & attendance'), $lp ?: null) ?>
        <?= $link('/app/loans', 'loans', 'bank', t('Loans & advances')) ?>
        <?= $link('/app/documents', 'documents', 'award', t('Documents'), $dr ?: null) ?>
        <div class="nav-label"><?= e(t('Automation')) ?></div>
        <?= $link('/app/assistant', 'assistant', 'zap', t('Smart assistant')) ?>
        <?php if (company_role($cu) === 'owner'): ?><?= $link('/app/automation', 'automation', 'clock', t('Auto payroll')) ?><?php endif; ?>
        <?= $link('/app/employees/import', 'import', 'upload', t('Smart import')) ?>
        <?php if (company_role($cu) === 'owner'): ?>
        <div class="nav-label"><?= e(t('Company')) ?></div>
        <?= $link('/app/settings', 'settings', 'settings', t('Settings')) ?>
        <?= $link('/app/team', 'team', 'users', t('Team')) ?>
        <?php if (billing_enabled()): ?><?= $link('/app/billing', 'billing', 'wallet', t('Plan & billing')) ?><?php endif; ?>
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
        <div class="nav-label"><?= e(t('Site')) ?></div>
        <?= $link('/admin/blog', 'admin-blog', 'file', t('Blog')) ?>
        <?= $link('/admin/seo', 'admin-seo', 'globe', t('SEO & AI search')) ?>
        <?= $link('/admin/code', 'admin-code', 'code', t('Code & tracking')) ?>
        <?= $link('/admin/fields', 'admin-fields', 'edit', t('Custom fields')) ?>
        <?= $link('/admin/security', 'admin-security', 'lock', t('Security')) ?>
        <?= $link('/admin/billing', 'admin-billing', 'wallet', t('Billing & plans')) ?>
        <?= $link('/admin/settings', 'admin-settings', 'settings', t('Platform settings')) ?>
      <?php endif; ?>
      <div class="nav-label"><?= e(t('Tools')) ?></div>
      <?= $link('/verify', 'verify', 'qr', t('Verify a payslip')) ?>
      <?= $link('/account', 'account', 'user', t('My account')) ?>
    </nav>
    <div class="side-foot">
      <div class="side-user">
        <div class="avatar"><?= e(initials($cu['name'])) ?></div>
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
        <button class="btn btn-ghost btn-icon menu-toggle" data-nav-toggle aria-label="Menu"><?= icon('menu', 22) ?></button>
        <div class="crumbs"><b><?= e($title ?? '') ?></b></div>
      </div>
      <div class="row" style="gap:8px">
        <?php partial('themetoggle'); ?>
        <?php partial('langswitch'); ?>
      </div>
    </header>
    <div class="content"><?= $content ?></div>
  </div>
</div>
<?php if (setting('chat_in_app', '0') === '1' && $cu['role'] !== 'admin') partial('chatwidget'); ?>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if ($appCode) echo footer_tracking_code(); ?>
</body>
</html>
