<?php $se = setting('support_email', ''); ?>
<div class="page-narrow prose">
  <h1>Terms of Service</h1>
  <p class="muted">Last updated: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p>By creating an account or using <?= e(site_name()) ?> you agree to these terms.</p>

  <h2>1. The service</h2>
  <p><?= e(site_name()) ?> lets employers record employees, run monthly payroll, and issue payslips that carry a unique code, a QR code and a digital fingerprint so that third parties can check them. The service is currently in beta and provided free of charge. We may introduce paid plans later and will give notice before any charge applies.</p>

  <h2>2. Employer responsibilities</h2>
  <ul>
    <li>You must be authorised to act for the company you register and must only enter real employees and accurate figures.</li>
    <li>You are responsible for your payroll calculations, tax deductions and compliance with Bangladesh labour and tax law. <?= e(site_name()) ?> is a tool and does not give legal, tax or accounting advice.</li>
    <li>You must have a lawful basis to enter your employees' personal data and must tell them that you use this service.</li>
    <li>If a payslip was issued in error, you must revoke it promptly.</li>
  </ul>

  <h2>3. Prohibited use</h2>
  <p>You must not use the service to create payslips for people who are not your employees, to overstate income, or to mislead any bank, embassy, landlord or other party. We will suspend accounts involved in fraud and may cooperate with authorities.</p>

  <h2>4. What verification means</h2>
  <p>A "genuine" result confirms that the payslip was issued through <?= e(site_name()) ?> by the named employer account and has not changed since. It does not mean we have audited the employer, its payroll or the employee's employment. Verifiers should use their own judgement and procedures.</p>

  <h2>5. Accounts and security</h2>
  <p>Keep your password confidential and tell us about any unauthorised use. We may suspend accounts that break these terms or put other users at risk.</p>

  <h2>6. Availability and liability</h2>
  <p>We work to keep the service available and accurate but provide it "as is" during the beta. To the extent permitted by law, we are not liable for indirect losses, and our total liability is limited to the amount you paid us in the 12 months before the claim.</p>

  <h2>7. Ending your account</h2>
  <p>You can stop using the service at any time. Contact us to close your company account. Issued payslips will stop verifying once your data is deleted.</p>

  <h2>8. Governing law</h2>
  <p>These terms are governed by the laws of Bangladesh.</p>

  <h2>9. Contact</h2>
  <p><?= $se ? 'Email <a href="mailto:' . e($se) . '">' . e($se) . '</a>.' : 'Use the contact link in the footer.' ?></p>
</div>
