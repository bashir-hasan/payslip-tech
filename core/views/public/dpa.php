<?php $se = setting('support_email', '') ?: 'contact@payslip.tech'; $who = setting('legal_entity', '') ?: 'Md Bashir Hasan Saikat, Dhaka, Bangladesh'; ?>
<div class="page-narrow prose">
  <h1>Data Processing Agreement</h1>
  <p class="muted">Last updated: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p>This Data Processing Agreement ("DPA") forms part of the <a href="<?= e(url('/terms')) ?>">Terms of Service</a> between the business using <?= e(site_name()) ?> (the "Customer", controller) and <?= e($who) ?> operating <?= e(site_name()) ?> (the "Processor"). It applies whenever the Customer's use of the service involves personal data covered by the EU or UK GDPR. By using the service the Customer accepts this DPA. If you need a signed copy, email <a href="mailto:<?= e($se) ?>"><?= e($se) ?></a>.</p>

  <h2>1. Subject matter and details</h2>
  <ul>
    <li><b>Nature and purpose:</b> calculating payroll; issuing, storing and letting third parties verify payslips and employment documents; leave, loan and team management; the employee portal.</li>
    <li><b>Duration:</b> while the Customer's account is open, then deletion as in section 8.</li>
    <li><b>Data subjects:</b> the Customer's employees, former employees and team members.</li>
    <li><b>Personal data:</b> identification and contact details, job details, salary and deductions, bank or wallet details, leave and loan records, documents issued.</li>
    <li><b>Special categories:</b> none are required. The Customer should not enter health or other special category data in free-text fields.</li>
  </ul>

  <h2>2. Instructions</h2>
  <p>The Processor processes personal data only on the Customer's documented instructions, which are the Customer's use of the service and these terms, unless the law requires otherwise, in which case the Processor will tell the Customer first where the law allows.</p>

  <h2>3. Confidentiality</h2>
  <p>Anyone authorised to process the data is bound by confidentiality.</p>

  <h2>4. Security (Art. 32)</h2>
  <p>HTTPS for all traffic; hashed passwords and optional two-factor sign-in; role-based access with company separation; CSRF protection; encryption of sensitive settings; keyed fingerprints on every payslip and document; rate limiting; daily backups by the hosting provider; audit logs of account actions.</p>

  <h2>5. Sub-processors</h2>
  <p>The Customer authorises the sub-processors listed in the <a href="<?= e(url('/privacy#sharing')) ?>">Privacy Policy</a>. The Processor will give at least 30 days' notice by email before adding or replacing one, and the Customer may object and close its account if it does. The Processor imposes data protection terms on each sub-processor that are no less protective than this DPA.</p>

  <h2>6. International transfers</h2>
  <p>Where personal data is transferred from the EEA, UK or Switzerland to a country without an adequacy decision, the parties agree that the European Commission's Standard Contractual Clauses (Module 2, controller to processor), and the UK International Data Transfer Addendum where relevant, are incorporated by reference.</p>

  <h2>7. Assistance</h2>
  <p>The Processor helps the Customer respond to data subject requests (the service lets the Customer correct, export and erase employee data itself), and with security, breach notification, impact assessments and consultations, taking into account the nature of the processing.</p>

  <h2>8. Breach notification</h2>
  <p>The Processor will notify the Customer without undue delay, and where possible within 48 hours, after becoming aware of a personal data breach affecting the Customer's data, with the information available at the time.</p>

  <h2>9. Deletion and return</h2>
  <p>The Customer can export its data at any time. When the account owner closes the company account, the Processor deletes the Customer's personal data at once, except where the law requires it to be kept.</p>

  <h2>10. Audits</h2>
  <p>The Processor makes available the information needed to show compliance with this DPA and allows for reasonable audits, normally by answering a written questionnaire, at most once a year unless a breach has occurred.</p>
</div>
