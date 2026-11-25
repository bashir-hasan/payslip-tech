<?php
$cc = client_country();
$heroCur = $cc === 'BD' ? 'BDT' : ($cc === 'IN' ? 'INR' : ($cc === 'GB' ? 'GBP' : (in_array($cc, ['DE','FR','NL','IE','ES','IT','BE','AT','PT','FI'], true) ? 'EUR' : ($cc === 'CA' ? 'CAD' : 'USD'))));
$hl = label_presets()[['BDT' => 'south_asia', 'INR' => 'india', 'GBP' => 'uk', 'EUR' => 'europe', 'CAD' => 'canada'][$heroCur] ?? 'us'];
$heroAmt = fn($v) => currency_symbol($heroCur) . money_fmt(round($v * ($heroCur === 'BDT' ? 1 : ($heroCur === 'INR' ? 0.7 : 0.05))), false, null, $heroCur);
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><?= icon('globe', 15) ?> <?= e(t('For teams in the US, Canada, Europe & South Asia')) ?></span>
      <h1><?= t('Payslips that <em>prove</em> they are real.') ?></h1>
      <p class="lead"><?= e(t('Run payroll, issue tamper-proof payslips and pay stubs with a QR code, and let banks, landlords, embassies and employers confirm they are genuine in one scan. Any currency. Free for small teams.')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>"><?= e(t('Create free account')) ?> <?= icon('arrow-right') ?></a>
        <?php if ($demo): ?><a class="btn btn-light btn-lg" href="<?= e(url('/demo')) ?>"><?= icon('eye') ?> <?= e(t('Try the live demo')) ?></a><?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/verify')) ?>"><?= icon('qr') ?> <?= e(t('Verify a payslip')) ?></a>
      </div>
      <div class="hero-trust">
        <span><?= icon('check', 16) ?> <?= e(t('No credit card')) ?></span>
        <span><?= icon('check', 16) ?> <?= e(t('20 currencies')) ?></span>
        <span><?= icon('check', 16) ?> <?= e(t('English & বাংলা')) ?></span>
        <span><?= icon('check', 16) ?> <?= e(t('Works on any phone')) ?></span>
      </div>
    </div>
    <div class="mock-wrap" aria-hidden="true">
      <div class="mock-badge"><span class="dot"><?= icon('check', 16) ?></span><div><b><?= e(t('Genuine payslip')) ?></b><br><span style="color:#9fb5b0;font-size:12px;background:var(--panel)"><?= e(t('Verified just now')) ?></span></div></div>
      <div class="mock">
        <div class="mock-top">
          <div><div class="mock-co"><?= $heroCur === 'BDT' ? 'Nodi Digital Ltd.' : 'Brightline Studio' ?></div><div class="mock-sub"><?= $heroCur === 'BDT' ? 'Dhanmondi, Dhaka' : ($heroCur === 'GBP' ? 'London, UK' : ($heroCur === 'CAD' ? 'Toronto, ON' : ($heroCur === 'EUR' ? 'Berlin, DE' : ($heroCur === 'INR' ? 'Bengaluru, IN' : 'Austin, TX')))) ?></div></div>
          <div style="text-align:right"><div class="mock-co" style="font-size:13px;letter-spacing:.06em"><?= e(in_array($heroCur, ['USD', 'CAD'], true) && lang() === 'en' ? 'PAY STUB' : t('PAYSLIP')) ?></div><div class="mock-sub"><?= e(period_label(date('Y-m', strtotime('first day of last month')))) ?></div></div>
        </div>
        <div class="mock-lines">
          <div><span><?= e(t($hl['basic'])) ?></span><span><?= e($heroAmt(36000)) ?></span></div>
          <div><span><?= e(t($hl['tax'])) ?></span><span><?= e($heroAmt(1800)) ?></span></div>
          <div><span><?= e(t($hl['house_rent'])) ?></span><span><?= e($heroAmt(18000)) ?></span></div>
          <div><span><?= e(t($hl['pf'])) ?></span><span><?= e($heroAmt(1800)) ?></span></div>
          <div><span><?= e(t($hl['medical'])) ?></span><span><?= e($heroAmt(3000)) ?></span></div>
          <div><span><?= e(t($hl['loan'])) ?></span><span><?= e($heroAmt(0)) ?></span></div>
        </div>
        <div class="mock-net"><span><?= e(t('Net Pay')) ?></span><b><?= e($heroAmt(56400)) ?></b></div>
        <div class="mock-foot"><div class="mock-qr"></div><div><b class="mono" style="font-size:13px">PS-7KQM-4XHD</b><div class="mock-sub"><?= e(t('Scan to verify')) ?> · payslip.tech/v/…</div></div></div>
      </div>
      <div class="mock-scan"><?= icon('bank', 18) ?> <span><?= e(t('Bank officer scans · result in seconds')) ?></span></div>
    </div>
  </div>
</section>

<?php if ($stats['payslips'] > 0): ?>
<section class="container" style="margin-top:10px">
  <div class="stats-band">
    <div><b><?= e(num(number_format($stats['companies']))) ?></b><span><?= e(t('companies issuing payslips')) ?></span></div>
    <div><b><?= e(num(number_format($stats['payslips']))) ?></b><span><?= e(t('verified payslips issued')) ?></span></div>
    <div><b><?= e(num(number_format($stats['verifications']))) ?></b><span><?= e(t('verification checks')) ?></span></div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-bottom:0">
  <div class="container">
    <div class="definition">
      <h2><?= e(t('What is PaySlip.tech?')) ?></h2>
      <p><?= e($faqs[0][1]) ?></p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="problem">
      <div>
        <span class="kicker" style="color:var(--lime)"><?= e(t('The problem')) ?></span>
        <h2><?= e(t('A payslip is easy to fake, and hard to check.')) ?></h2>
        <p><?= e(t('Banks, embassies, landlords and new employers ask for payslips every day. Most are Word or Excel files, or paper with a stamp. Anyone can edit the numbers, and checking means phoning HR and waiting.')) ?></p>
      </div>
      <div class="problem-list">
        <div class="problem-item"><?= icon('alert') ?><div><b><?= e(t('Fake payslips take minutes to make')) ?></b><span><?= e(t('Online generators produce convincing payslips for any company.')) ?></span></div></div>
        <div class="problem-item"><?= icon('clock') ?><div><b><?= e(t('Checking takes days')) ?></b><span><?= e(t('Loan officers and visa offices call employers one by one.')) ?></span></div></div>
        <div class="problem-item"><?= icon('users') ?><div><b><?= e(t('Honest employees pay the price')) ?></b><span><?= e(t('Genuine applicants face delays and extra paperwork.')) ?></span></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section" id="how" style="padding-top:20px">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(t('How it works')) ?></span>
      <h2><?= e(t('Payroll in, verified payslips out.')) ?></h2>
      <p><?= e(t('Three steps. No hardware, no paperwork, no IT team.')) ?></p>
    </div>
    <div class="steps">
      <div class="step"><h3><?= e(t('Add your team')) ?></h3><p><?= e(t('Enter employees once with their salary structure, or import a CSV. Base pay, allowances, tax, retirement and other deductions use the local terms of your country.')) ?></p></div>
      <div class="step"><h3><?= e(t('Run monthly payroll')) ?></h3><p><?= e(t('Add bonus, overtime, advances or unpaid days, review the totals, then finalise. Export a payment sheet for your bank or mobile wallet in one click.')) ?></p></div>
      <div class="step"><h3><?= e(t('Issue verified payslips')) ?></h3><p><?= e(t('Every payslip is locked with a unique code, a QR code and a digital fingerprint. Anyone can scan it to confirm it is genuine and unchanged.')) ?></p></div>
    </div>
  </div>
</section>

<section class="section" id="features" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(t('More than payslips')) ?></span>
      <h2><?= e(t('Everything a small office needs to pay people and prove it')) ?></h2>
      <p><?= e(t('Payroll, HR paperwork and proof of income in one place, all verifiable by QR code.')) ?></p>
    </div>
    <div class="feat-grid">
      <?php foreach ([
        ['award', t('Verified letters and certificates'), t('Employment letters, salary certificates, increment letters, income and tax statements. Each has its own QR code, so embassies and banks can check it in seconds.')],
        ['send', t('Proof-of-income links'), t('Employees send one link with several months of payslips, checked live. They choose to show amounts, only "earns at least X", or nothing, and can switch the link off.')],
        ['shield', t('Employer trust check'), t('Every check shows whether the employer owns its domain, was reviewed by us, and how long it has run payroll. Fake companies stand out.')],
        ['users', t('Team roles and approval'), t('HR prepares payroll, the accountant approves it. Each person has their own sign-in, and every action is logged.')],
        ['calendar', t('Leave, advances and festival bonus'), t('Leave requests and balances, salary advances repaid in instalments, and Eid bonus for everyone in one click.')],
        ['bank', t('Pay and report in minutes'), t('Bank and bKash/Nagad payment files, payslips sent on WhatsApp, Excel reports, and reminders before the legal pay deadline.')],
      ] as [$ic, $h, $p]): ?>
        <div class="feat"><div class="ic-wrap"><?= icon($ic, 20) ?></div><h3><?= e($h) ?></h3><p><?= e($p) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="audience">
      <div class="aud"><div class="ic-wrap"><?= icon('building', 22) ?></div><h3><?= e(t('For employers')) ?></h3><p class="muted"><?= e(t('Simple payroll for teams of 2 to 250.')) ?></p>
        <ul><li><?= icon('check', 16) ?><?= e(t('Local terms: Pay Stub, PAYE, CPP/EI, PF…')) ?></li><li><?= icon('check', 16) ?><?= e(t('Any currency, payment sheet export')) ?></li><li><?= icon('check', 16) ?><?= e(t('Revoke a payslip instantly')) ?></li><li><?= icon('check', 16) ?><?= e(t('See who verified each payslip')) ?></li></ul></div>
      <div class="aud"><div class="ic-wrap"><?= icon('user', 22) ?></div><h3><?= e(t('For employees')) ?></h3><p class="muted"><?= e(t('Your payslips, always in your pocket.')) ?></p>
        <ul><li><?= icon('check', 16) ?><?= e(t('Download any month as PDF')) ?></li><li><?= icon('check', 16) ?><?= e(t('Share a verification link')) ?></li><li><?= icon('check', 16) ?><?= e(t('Faster loan, rental and visa approvals')) ?></li><li><?= icon('check', 16) ?><?= e(t('No more asking HR for copies')) ?></li></ul></div>
      <div class="aud"><div class="ic-wrap"><?= icon('bank', 22) ?></div><h3><?= e(t('For verifiers')) ?></h3><p class="muted"><?= e(t('Banks, embassies, landlords, HR.')) ?></p>
        <ul><li><?= icon('check', 16) ?><?= e(t('Scan the QR, see the result instantly')) ?></li><li><?= icon('check', 16) ?><?= e(t('Spot edited or revoked payslips')) ?></li><li><?= icon('check', 16) ?><?= e(t('Free, no account needed')) ?></li><li><?= icon('check', 16) ?><?= e(t('JSON API for bulk checks')) ?></li></ul></div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head" style="margin-bottom:22px"><span class="kicker"><?= e(t('Worldwide')) ?></span><h2><?= e(t('Local payslips, wherever your team works')) ?></h2><p><?= e(t('Pick your country and PaySlip.tech uses its currency and payslip terms automatically.')) ?></p></div>
    <div class="country-chips">
      <?php foreach ([['🇺🇸', 'United States', 'USD · Pay Stub · FICA'], ['🇨🇦', 'Canada', 'CAD · CPP / EI'], ['🇬🇧', 'United Kingdom', 'GBP · PAYE · NI'], ['🇪🇺', 'Europe', 'EUR · SEK · PLN · CHF'], ['🇧🇩', 'Bangladesh', 'BDT · বাংলা'], ['🇮🇳', 'India', 'INR · EPF · TDS'], ['🇵🇰', 'Pakistan', 'PKR · EOBI'], ['🇱🇰', 'Sri Lanka & Nepal', 'LKR · NPR'], ['🇦🇪', 'Gulf', 'AED · SAR'], ['🇦🇺', 'Australia & NZ', 'AUD · NZD']] as [$f, $n, $d]): ?>
        <div class="chip"><span class="flag"><?= $f ?></span><div><b><?= e($n) ?></b><span><?= e($d) ?></span></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="verify-band">
      <div>
        <h2><?= e(t('Got a payslip to check?')) ?></h2>
        <p style="margin:0"><?= e(t('Enter the code printed under the QR code, or scan it with your phone camera.')) ?></p>
      </div>
      <form class="verify-form" method="post" action="<?= e(url('/verify')) ?>">
        <?= csrf_field() ?>
        <input class="input" name="code" placeholder="PS-XXXX-XXXX" aria-label="<?= e(t('Verification code')) ?>" autocomplete="off" required>
        <button class="btn btn-dark btn-lg"><?= icon('shield') ?> <?= e(t('Verify')) ?></button>
      </form>
    </div>
  </div>
</section>

<section class="section" id="pricing">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(t('Pricing')) ?></span>
      <h2><?= e(t('Simple pricing. Verification is always free.')) ?></h2>
      <p><?= e(t('Start free. Upgrade when your team grows. Prices in US dollars; tax or VAT is calculated at checkout.')) ?></p>
    </div>
    <div class="pricing">
      <?php foreach ($plans as $k => $p): ?>
        <div class="plan <?= $p['badge'] ? 'featured' : '' ?>"><?php if ($p['badge']): ?><span class="tag"><?= e($p['badge']) ?></span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <div class="price">$<?= e(rtrim(rtrim(number_format((float)$p['price'], 2), '0'), '.')) ?> <small><?= e(t('/ month')) ?></small></div>
          <ul><?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?><li><?= icon('check', 16) ?><?= e($f) ?></li><?php endforeach; ?></ul>
          <a class="btn <?= $p['badge'] ? 'btn-primary' : 'btn-light' ?> btn-block" href="<?= e(url('/register')) ?>"><?= e($k === 'free' ? t('Start free') : sprintf(t('Start with %s'), $p['name'])) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="center small muted" style="margin-top:16px"><?= e(t('Banks, landlords and embassies verify payslips free, with no account. Need the API for your institution?')) ?> <a href="<?= e(url('/developers')) ?>"><?= e(t('Read the API docs')) ?></a></p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><span class="kicker"><?= e(t('Questions')) ?></span><h2><?= e(t('Frequently asked')) ?></h2></div>
    <div class="faq">
      <?php foreach ($faqs as $i => [$q, $ans]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?></summary><p><?= e($ans) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
