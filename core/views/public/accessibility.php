<?php $se = setting('support_email', '') ?: 'contact@payslip.tech'; ?>
<div class="page-narrow prose">
  <h1>Accessibility Statement</h1>
  <p class="muted">Last reviewed: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p>We want everyone to be able to run payroll, read their payslips and verify documents on <?= e(site_name()) ?>, including people who use screen readers, keyboards, magnification or voice control.</p>
  <h2>Our target</h2>
  <p>We aim to meet the <b>Web Content Accessibility Guidelines (WCAG) 2.2, level AA</b>. We test new pages with automated tools (axe-core) and by hand with the keyboard.</p>
  <h2>What we have done</h2>
  <ul>
    <li>A "Skip to main content" link and clear page landmarks on every page.</li>
    <li>Every form field has a label that screen readers announce; errors are shown in text, not only colour.</li>
    <li>Visible focus outlines for keyboard users, and all features work without a mouse.</li>
    <li>Text and controls meet AA colour contrast in both light and dark mode.</li>
    <li>Pages work at 200% zoom and on small screens, and respect the "reduce motion" setting.</li>
    <li>Payslips and documents are real text, not images, and can be read in English or Bangla.</li>
    <li>QR codes always come with a written verification code and link, so nobody needs a camera to verify.</li>
  </ul>
  <h2>Known limitations</h2>
  <ul>
    <li>Tracking or chat code added by a site owner from another provider may not meet the same standard.</li>
    <li>Downloaded PDF payslips are created by your browser's print feature and may not include full PDF tagging.</li>
  </ul>
  <h2>Feedback</h2>
  <p>If something is hard to use, email <a href="mailto:<?= e($se) ?>"><?= e($se) ?></a> and tell us the page and what you were trying to do. We aim to reply within 5 working days and will help you complete the task another way in the meantime.</p>
</div>
