<?php $se = setting('support_email', '') ?: 'contact@payslip.tech'; $mail = '<a href="mailto:' . e($se) . '">' . e($se) . '</a>'; $who = setting('legal_entity', '') ?: 'Md Bashir Hasan Saikat, Dhaka, Bangladesh'; ?>
<div class="page-narrow prose">
  <h1>Privacy Policy</h1>
  <p class="muted">Last updated: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p>This policy explains how <?= e(site_name()) ?> handles personal data when employers run payroll and issue verifiable documents, when employees use their portal, and when anyone verifies a payslip or document. It is written to meet the EU and UK General Data Protection Regulation (GDPR), and also applies to users everywhere else.</p>

  <h2 id="who">1. Who we are and our role</h2>
  <p><?= e(site_name()) ?> is operated by <?= e($who) ?> ("we", "us"). Contact: <?= $mail ?>.</p>
  <ul>
    <li><b>Employers are the controllers</b> of the employee, salary, leave, loan and document data they enter. We process that data only on their instructions, as their <b>processor</b>, under our <a href="<?= e(url('/dpa')) ?>">Data Processing Agreement</a>.</li>
    <li><b>We are the controller</b> for account data of our own users (name, email, password), billing records, support chat, security logs and verification logs.</li>
  </ul>

  <h2 id="data">2. What we collect</h2>
  <ul>
    <li><b>Employer and team accounts:</b> company name, address, contact details, tax ID, website domain, logo, seal and signature images, signatory name, and each team member's name, email, role and hashed password.</li>
    <li><b>Employee records entered by employers:</b> name, employee ID, designation, department, joining date, email, phone, payment method, bank or mobile wallet details, salary structure, monthly payroll figures, salary changes, leave requests and balances, loans and advances, and documents issued (letters, certificates, statements).</li>
    <li><b>Employee portal accounts:</b> name, email, hashed password, and the proof-of-income links an employee creates (which payslips and documents they include, expiry date, how often they were opened).</li>
    <li><b>Verification checks:</b> the code checked, the result, time, IP address, approximate country, browser user-agent and, if given, the type of verifier (for example "bank"). We never ask verifiers for their name.</li>
    <li><b>Billing:</b> plan, subscription status and Polar customer ID. Card details are entered on Polar's checkout and never reach our servers.</li>
    <li><b>Support chat:</b> the messages you send and, if you give them, your name and email.</li>
    <li><b>Security logs:</b> sign-in times, IP address and actions taken in an account (for example "issued payslips").</li>
    <li><b>Site statistics (no cookies):</b> the pages you open, how long you stay and how far you scroll, the site that sent you, campaign tags in the link, approximate country (and city, if our network provider supplies it), device type, browser, operating system and language. You are counted with a code made from your IP address and browser that changes every day, so we cannot follow you from one day to the next, and your IP address is not stored. If you are signed in, the visit is linked to your account so we can give support and keep accounts secure. Search engine and AI crawlers are counted by name only. We keep these statistics for 13 months. If your browser sends Do Not Track or Global Privacy Control, nothing is recorded.</li>
    <li><b>Cookies:</b> see our <a href="<?= e(url('/cookies')) ?>">Cookie Policy</a>. Third-party analytics such as Google Analytics run only if you accept them.</li>
  </ul>

  <h2 id="bases">3. Why we use it and our legal bases</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th scope="col">Purpose</th><th scope="col">Legal basis (GDPR Art. 6)</th></tr></thead>
    <tbody>
      <tr><td>Providing the service: payroll, payslips, documents, the employee portal, team access</td><td>Contract (6(1)(b)); for employee data, on the employer's instructions</td></tr>
      <tr><td>Letting anyone holding a code confirm a document is genuine, and showing employers their verification log</td><td>Legitimate interests (6(1)(f)): preventing document fraud</td></tr>
      <tr><td>Security, abuse prevention, rate limiting and audit logs</td><td>Legitimate interests (6(1)(f)); legal obligation where applicable (6(1)(c))</td></tr>
      <tr><td>Billing and accounting records</td><td>Contract (6(1)(b)) and legal obligation (6(1)(c))</td></tr>
      <tr><td>Account emails (invitations, password resets, reminders you switch on)</td><td>Contract (6(1)(b))</td></tr>
      <tr><td>Cookieless site statistics on our own server, to see which pages help people and fix what doesn't</td><td>Legitimate interests (6(1)(f)); you can opt out with Do Not Track or Global Privacy Control</td></tr>
      <tr><td>Third-party analytics and marketing tags (for example Google Analytics)</td><td>Consent (6(1)(a)), which you can withdraw at any time</td></tr>
    </tbody>
  </table></div>
  <p>We do not sell personal data, do not use it for advertising, and do not use it to train AI models. The smart assistant and smart import run on our own server; every change they suggest is shown to you before it is applied, so no decision with legal effect is made automatically.</p>

  <h2 id="verifiers">4. What verifiers can see</h2>
  <p>Anyone with a payslip or document code can see the employer's name, the employee's name, ID and designation, the period or document type, the issue date, the code and fingerprint, and a summary of how far the employer itself has been checked. Pay amounts appear only if the employer allows it, or if the employee chose to show them in a proof-of-income link. <b>Bank account and wallet numbers are never shown.</b> Employers can revoke any payslip or document, and employees can switch off their links at any time.</p>

  <h2 id="sharing">5. Who we share data with</h2>
  <p>We use these service providers (sub-processors) to run the service:</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th scope="col">Provider</th><th scope="col">What for</th><th scope="col">Location</th></tr></thead>
    <tbody>
      <tr><td>Our web hosting provider</td><td>Servers and database, email sending</td><td>Data centre outside the EU</td></tr>
      <tr><td>Cloudflare, Inc.</td><td>Security, DNS and fast delivery of pages</td><td>Global network, USA</td></tr>
      <tr><td>Polar Software Inc.</td><td>Merchant of record for paid plans</td><td>USA / EU</td></tr>
    </tbody>
  </table></div>
  <p>We share data with authorities only when the law requires it. We will tell employers before adding a new sub-processor, as set out in the DPA.</p>

  <h2 id="transfers">6. International transfers</h2>
  <p>We are based in Bangladesh and our servers are outside the European Economic Area. Where personal data from the EEA, UK or Switzerland is transferred, we rely on the European Commission's Standard Contractual Clauses (and the UK Addendum) with our providers and with employers who sign our DPA, together with the security measures below.</p>

  <h2 id="retention">7. How long we keep data</h2>
  <ul>
    <li>Issued payslips and documents: for as long as the employer's account is open, so they stay verifiable, and for any period the employer must keep payroll records by law.</li>
    <li>Verification logs: IP addresses and browser details are removed after 6 months; the rest of the log is deleted after 24 months.</li>
    <li>Security and activity logs: IP addresses removed after 12 months.</li>
    <li>Support chats: deleted 12 months after the last message.</li>
    <li>Proof-of-income links: stop working on their expiry date (7 to 90 days) or when switched off.</li>
    <li>Closed accounts: when an owner closes a company account, its data is deleted at once, except billing records we must keep for tax purposes.</li>
  </ul>

  <h2 id="rights">8. Your rights</h2>
  <p>You have the right to access, correct, delete or restrict your data, to object to processing based on legitimate interests, to data portability, and to withdraw consent at any time. You can:</p>
  <ul>
    <li><b>Download your data</b> as a file from <a href="<?= e(url('/account')) ?>">My account</a> (portability and access).</li>
    <li><b>Close a company account</b> and delete its data from My account (account owners).</li>
    <li><b>Change cookie choices</b> with the "Cookie settings" link at the bottom of every page.</li>
  </ul>
  <p>Employees: your employer controls your payroll records, so please contact them first; they can correct or erase your data in <?= e(site_name()) ?>. You can also write to us at <?= $mail ?>. We reply within one month. You may also complain to your data protection authority, for example the authority in the EU country where you live or work.</p>

  <h2 id="security">9. Security</h2>
  <p>Data travels over HTTPS. Passwords are stored as one-way hashes, two-factor sign-in is available, every form is protected against cross-site request forgery, access is limited by role, each company can see only its own records, sensitive settings are encrypted, and payslips and documents carry a keyed digital fingerprint that reveals any change. If a breach affects personal data, we will notify affected employers without undue delay so they can meet their own duties.</p>

  <h2 id="children">10. Children</h2>
  <p>The service is for businesses and their staff and is not directed at children under 16.</p>

  <h2 id="changes">11. Changes</h2>
  <p>We will post changes on this page and update the date above. If a change is significant we will also email account owners.</p>
</div>
