<div class="page-narrow prose">
  <h1>Cookie Policy</h1>
  <p class="muted">Last updated: <?= e(date('j F Y', filemtime(__FILE__))) ?></p>
  <p><?= e(site_name()) ?> uses a small number of cookies. Essential cookies are always on because the site cannot work without them. Anything else runs only if you choose "Accept all".</p>
  <h2>Essential cookies</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Lasts</th></tr></thead>
    <tbody>
      <tr><td><code>__Host-pst</code> or <code>pst_sess</code></td><td>Keeps you signed in and protects forms against forgery</td><td>Until you close the browser or sign out</td></tr>
      <tr><td><code>pst_consent</code></td><td>Remembers your cookie choice</td><td>12 months</td></tr>
      <tr><td><code>pst_theme</code></td><td>Remembers light or dark mode, only if you switch it</td><td>12 months</td></tr>
      <tr><td><code>lang</code> (in the session)</td><td>Remembers English or Bangla</td><td>Session</td></tr>
    </tbody>
  </table></div>
  <h2>Analytics and marketing (optional)</h2>
  <?php if (has_custom_scripts()): ?>
    <p>If you accept, we load the analytics or marketing tools the site owner has set up<?= setting('ga4_id') || setting('gtm_id') ? ' (such as Google Analytics or Google Tag Manager)' : '' ?><?= setting('fb_pixel_id') ? ', Meta Pixel' : '' ?><?= setting('clarity_id') ? ', Microsoft Clarity' : '' ?>. These set their own cookies to count visits and see which pages are useful. They never load inside payslips or documents.</p>
  <?php else: ?>
    <p>We currently use no analytics or marketing cookies.</p>
  <?php endif; ?>
  <p>We use no advertising cookies. Fonts and scripts are served from our own server, so your browser does not contact Google Fonts or similar services.</p>
  <h2>Statistics without cookies</h2>
  <p>Our own visit statistics use no cookies and store nothing in your browser. Visitors are counted with a code that changes every day and IP addresses are not stored. Browsers that send Do Not Track or Global Privacy Control are not counted. See section 2 of our <a href="<?= e(url('/privacy')) ?>">Privacy Policy</a>.</p>
  <?php if (has_custom_scripts()): ?>
  <h2>Change your choice</h2>
  <p>Your current choice: <b><?= e(consent_state() === 'all' ? 'Accept all' : (consent_state() === 'essential' ? 'Essential only' : 'Not chosen yet')) ?></b>.</p>
  <p><button type="button" class="btn btn-light" data-consent-open><?= e(t('Cookie settings')) ?></button></p>
  <?php endif; ?>
</div>
