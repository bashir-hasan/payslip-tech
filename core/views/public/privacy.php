<?php $se = setting('support_email', ''); ?>
<div class="page-narrow prose">
  <h1>Privacy Policy</h1>
  <p class="muted">Last updated: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p>This policy explains how <?= e(site_name()) ?> ("we", "us") handles personal data when employers use our service to run payroll and issue verifiable payslips, when employees view their payslips, and when anyone verifies a payslip.</p>

  <h2>1. Our role</h2>
  <p>For employee and salary data that an employer enters, the <b>employer is responsible</b> for that data and we process it on their instructions to provide the service. For account data of our own users (name, email, password) and for verification logs, we are responsible.</p>

  <h2>2. What we collect</h2>
  <ul>
    <li><b>Employer accounts:</b> company name, address, contact details, tax ID (TIN), logo, signatory name, and the account holder's name, email and hashed password.</li>
    <li><b>Employee records entered by employers:</b> name, employee ID, designation, department, joining date, contact details, payment method, bank or mobile wallet details, salary structure and monthly payroll figures.</li>
    <li><b>Employee portal accounts:</b> name, email and hashed password.</li>
    <li><b>Verification checks:</b> the code checked, the result, time, IP address, browser user-agent and, if provided, the type of verifier (for example "bank"). We do not ask verifiers for their name.</li>
    <li><b>Technical data:</b> a session cookie needed to keep you signed in and a language preference cookie. We do not use advertising or tracking cookies.</li>
  </ul>

  <h2>3. How we use it</h2>
  <ul>
    <li>To calculate payroll, generate payslips and show them to the employer and the employee.</li>
    <li>To let anyone holding a payslip code confirm that it is genuine.</li>
    <li>To show employers a log of when their payslips were verified.</li>
    <li>To secure the service (rate limiting, preventing abuse) and to send account emails such as invitations and password resets.</li>
  </ul>
  <p>We do not sell personal data and we do not use it for advertising.</p>

  <h2>4. What a verifier can see</h2>
  <p>Anyone with a payslip's code can see: employer name, employee name and ID, designation, pay period, issue date, the verification code and fingerprint, and, only if the employer enables it, gross pay, total deductions and net pay. <b>Bank account and wallet numbers are never shown</b> on verification pages or in the API. Employers can turn off amounts in their settings and can revoke any payslip.</p>

  <h2>5. Sharing</h2>
  <p>We share data only with service providers needed to run the service (our web host and email delivery), and with authorities when the law requires it.</p>

  <h2>6. Security</h2>
  <p>Passwords are stored as one-way hashes. All forms are protected against cross-site request forgery, access is limited by role, each company can only access its own records, and payslips carry a keyed digital fingerprint that detects changes. No system is perfectly secure; please tell us immediately if you suspect a problem.</p>

  <h2>7. Retention</h2>
  <p>Issued payslips are kept for as long as the employer's account is active, so that they remain verifiable. Employers can deactivate employees at any time. If an employer closes their account, we delete or anonymise their data within 90 days, except where we must keep records by law. Verification logs are kept for up to 24 months.</p>

  <h2>8. Your rights</h2>
  <p>You can ask us to access, correct or delete your personal data. Employees should first contact their employer, who controls their payroll records. Contact us<?= $se ? ' at <a href="mailto:' . e($se) . '">' . e($se) . '</a>' : '' ?> and we will respond within 30 days.</p>

  <h2>9. Changes</h2>
  <p>We will post any changes on this page and update the date above.</p>
</div>
