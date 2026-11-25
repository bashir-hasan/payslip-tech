# PaySlip.tech: installation and operations guide (v1.4.0)

Payroll + tamper-proof, QR-verifiable payslips. 20 currencies, local payslip terms (US, Canada, UK, EU,
South Asia, Gulf, AU/NZ), English & Bangla, light/dark mode, Polar.sh subscriptions.
Plain PHP 8.1+ and MySQL/MariaDB. No Composer, no build step.

---------------------------------------------------------------------------------------------------
## A. Upgrading your live site from v1.0.x / v1.2.x / v1.3.x (no reinstall, no data loss)

1. **Back up first:** cPanel → Backup (or phpMyAdmin → Export) for the database, and download `config.php`.
2. Upload `payslip-tech-v1.4.0.zip` to the site folder and **Extract**, choosing to overwrite files.
   - Your `config.php` is not in the zip, so it is kept.
   - If your old `.htaccess` had a block starting `# php -- BEGIN cPanel-generated handler`, copy that block
     back to the **bottom** of the new `.htaccess` (or re-select the PHP version in MultiPHP Manager).
3. If an old `app/` folder still exists, delete it (the code now lives in `core/`).
4. Open the site once. The database upgrades itself automatically (adds 2FA, custom fields, countries,
   currencies, billing, automation and live-chat tables, and loads the chatbot's starter answers).
   Existing payslips keep verifying.
5. Delete `install.php` if it was uploaded again.
6. Sign in as admin → **My account** → turn on **two-factor authentication**.

## B. Fresh install

1. cPanel → MySQL Databases: create database + user, grant ALL PRIVILEGES.
2. Upload and extract the zip into the domain's document root; choose PHP 8.2+ (pdo_mysql, mbstring, openssl, curl, gd).
3. Visit `https://your-domain/install.php`, fill in the form, keep "Create a demo company" ticked.
4. Delete `install.php`. Turn on 2FA for your admin account.

---------------------------------------------------------------------------------------------------
## C. Cloudflare setup (recommended)

1. Add the domain to Cloudflare and switch nameservers at your registrar.
2. **SSL/TLS → Overview → Full (strict).** In cPanel keep AutoSSL on (or install a Cloudflare Origin Certificate).
3. **SSL/TLS → Edge Certificates:** Always Use HTTPS = On, Minimum TLS = 1.2, TLS 1.3 = On, HSTS optional.
4. **Caching → Cache Rules:** create a rule "Bypass cache" when *URI Path* starts with any of
   `/app` `/admin` `/me` `/account` `/login` `/register` `/payslip` `/v/` `/api/` `/webhooks/` `/invite` `/reset`.
   (Static files in `/assets/` can be cached; HTML pages should not be cached because they contain personal data.)
5. **Security → WAF → Rate limiting (free rule):** e.g. 20 requests / 1 minute per IP on `/login` and `/register`.
6. **Security → Bots:** Bot Fight Mode = On. *Exception:* it must not block `/webhooks/polar` (Polar's servers)
   — if Polar webhooks fail, add a WAF Skip rule for that path.
7. **Security → Settings:** Browser Integrity Check = On.
8. Do not enable "Rocket Loader" (it rewrites scripts and conflicts with the Content-Security-Policy).

The app automatically trusts `CF-Connecting-IP` **only** when the request really comes from Cloudflare's IP
ranges, so rate limits, audit logs and verification logs record the real visitor IP and country.

---------------------------------------------------------------------------------------------------
## D. Selling plans with Polar.sh

1. Create a Polar account at polar.sh. Connect your payout account (Bangladesh is supported via Stripe Connect Express).
2. Products → create **Starter** and **Business** with a monthly recurring price (default site prices: $9 and $29).
3. Settings → Developers → **New token** with scopes `checkouts:write`, `customer_sessions:write`, `products:read`.
4. Settings → Webhooks → **Add endpoint** `https://payslip.tech/webhooks/polar`, format *Raw*,
   select all `subscription.*` events. Copy the `whsec_…` secret.
5. In PaySlip.tech: **Admin → Billing & plans** → paste token, webhook secret and the two product IDs,
   set employee limits, tick "Test the Polar connection", then **Enable paid plans**.
6. Try it in **Sandbox mode** first (sandbox.polar.sh with test cards), then switch it off for live.

Polar is the Merchant of Record: it collects VAT/sales tax for US, Canada, UK and EU customers and sends you payouts.

---------------------------------------------------------------------------------------------------
## E. What's included

**Public:** landing page (EN/বাংলা), verify page, JSON API (`/api/v1/verify/{CODE}`), API docs, privacy, terms, one-click demo.
**Employer:** employees (+ CSV import, custom fields), monthly payroll with adjustments, verified payslips (QR + HMAC
fingerprint), revoke, email payslips, payment sheet CSV, verification log, company settings (country, currency,
line names, logo), plan & billing.
**Employee portal:** own payslips, PDF, share verification link.
**Super admin:** overview, companies (plan override), users, verifications, SEO & AI search, code & tracking,
custom fields, security centre, billing & plans, platform settings.

## F. SEO / AEO / GEO (AI search)

Unique titles/descriptions per page (editable in admin), canonical + hreflang (EN/BN), Open Graph + Twitter cards
with a 1200×630 image, JSON-LD (Organization, WebSite, SoftwareApplication with AggregateOffer, FAQPage,
BreadcrumbList, TechArticle), `/sitemap.xml`, `/robots.txt` (welcomes GPTBot, ClaudeBot, PerplexityBot,
Google-Extended…), `/llms.txt`, semantic HTML, fast pages, mobile-first, private pages `noindex`.
After launch: submit the sitemap in Google Search Console and Bing Webmaster Tools (verification codes go in
Admin → Code & tracking).

## G. Security

- Passwords: bcrypt/argon via `password_hash`; common-password blocklist; admin passwords ≥ 10 chars.
- Two-factor authentication (TOTP, any authenticator app) with one-time recovery codes; replay protection.
- Account lockout after 5 wrong passwords (15 min) + per-IP and per-account rate limits; equal-time responses.
- Sessions: HttpOnly, Secure, SameSite=Lax, `__Host-` cookie, strict mode, 2 h idle / 12 h absolute timeout,
  bound to the browser, all other sessions signed out on password or 2FA change.
- CSRF tokens on every form; prepared SQL statements everywhere; output escaping; honeypot on sign-up.
- Strict Content-Security-Policy (no inline scripts on dashboards), HSTS, X-Frame-Options, COOP,
  Referrer-Policy, Permissions-Policy.
- Uploaded logos are re-encoded with GD (strips hidden payloads); PHP cannot run in `/uploads`.
- `.htaccess` blocks `core/`, `config.php`, dotfiles, backups, SQL/logs and unused HTTP methods.
- Secrets (Polar token, webhook secret, 2FA keys) encrypted with AES-256-GCM using your `app_key`.
- Webhooks verified with HMAC (Standard Webhooks), 5-minute replay window, idempotent.
- Payslip integrity: HMAC-SHA256 fingerprint over the payslip contents (including currency).
- Audit log of sign-ins, failures, lockouts, password/2FA and admin changes (Admin → Security).

Keep `config.php` private and backed up: if `app_key` changes, issued payslips stop verifying.
No system is unbreakable — keep PHP updated, use a strong cPanel password with 2FA, and take regular backups.

---------------------------------------------------------------------------------------------------
## H. New in v1.3.0: automation, smart assistant, live chat

### 1. Fix "Email could not be sent from this server" (do this first)
Most shared hosts block PHP's `mail()`, so invites, payslip emails and chat alerts fail. Use SMTP:
1. cPanel → **Email Accounts** → create `no-reply@payslip.tech` with a strong password.
2. cPanel → Email Accounts → **Connect Devices** shows the SMTP server (usually `mail.payslip.tech`,
   port **465**, SSL).
3. Admin → **Platform settings** → *Email delivery*: Send method **SMTP**, enter host, port 465, SSL,
   username = the full address, and the password. Set *Send emails from* to the same address.
   Tick *Send a test email to me after saving* → Save. It should arrive within a minute.
4. Keep emails out of spam: cPanel → **Email Deliverability** shows SPF and DKIM records. Your DNS is on
   Cloudflare, so add them in Cloudflare → DNS as TXT records. Also add a `_dmarc` TXT record:
   `v=DMARC1; p=none; rua=mailto:you@yourmail.com`. The `mail` A record must be **DNS only**
   (grey cloud), otherwise SMTP cannot connect. Gmail, Zoho, Brevo or Resend SMTP also work.

### 2. Turn on the scheduler (needed for Auto payroll)
cPanel → **Cron Jobs** → *Once per hour* → paste the command shown (ready to copy) in
Admin → Platform settings → Scheduler, for example:
`/usr/local/bin/php /home/YOURUSER/public_html/cron.php >/dev/null 2>&1`
The same page shows *Last run* so you can confirm it works. `cron.php` refuses to run from a browser;
if your host only allows URL crons, use the secret `/cron/…` link shown there.

### 3. Automation features (no external AI API; everything runs on your server)
- **Smart assistant** (Employer → Smart assistant): plain English or Bangla commands, e.g.
  `Rahim bonus 5000, Sadia 2 unpaid days, Tanvir overtime 3,500 and advance 2k`, `রহিম বোনাস ৭০০০`,
  `give everyone 1000 bonus`, `run payroll for this month`, `finalise and email payslips`,
  `add employee Karim Ali as Driver salary 20000`, `what is the total payroll?`, `who has no email?`.
  It always shows a preview; nothing changes until **Apply**.
- **Auto payroll**: choose pay day; a draft is made N days before with an email reminder; on pay day
  payslips are finalised, issued and emailed automatically.
- **Smart import**: upload any .xlsx or .csv (English or Bangla headers; title rows are skipped).
  Columns are matched automatically and can be changed. A single *Salary/Gross* column is split into
  the salary structure; bonus/overtime/advance/unpaid-day columns go into the current draft payroll.
- **Explain my payslip**: a plain-language explanation, comparison with last month, and answers to
  common questions, for employees and employers.
- **Monthly limits** (editable in Admin → Billing & plans): Free 30, Starter 300, Business 2,000 smart
  actions. One applied assistant command, one import or one explanation = one action. Auto payroll is free.

### 4. Live chat + WhatsApp
- A chat button appears on every public page. The **bot answers first** from the knowledge base
  (English and Bangla, 40+ ready answers). When a visitor writes "talk to a human", the chat moves to
  **Admin → Live chat → Waiting for you** and you get an email. Reply there; the visitor sees it in the
  chat window (and by email if they left one).
- Admin → Live chat → *Chat settings*: add your **WhatsApp number** (e.g. 8801XXXXXXXXX) for a
  "Chat on WhatsApp" button; optionally show the chat inside the dashboards too.
- **Train the bot**: Admin → **Train chatbot**. Questions the bot could not answer are listed on the
  right: click **Answer**, write the reply, add keywords people might use → Save. It is used
  immediately. Edit, switch off or delete any answer at any time.

## I. New in v1.4.0: Blog (SEO articles)

Admin → **Blog** → *New article*. Write in simple formatting (## headings, - lists, **bold**,
[links](/verify), tables, `[[cta]]` for a sign-up box). HTML is not allowed, for security.
- Each article gets its own page at `/blog/your-slug`, Article + FAQ + breadcrumb schema, an
  Open Graph image (the cover), a sitemap entry, RSS (`/blog/feed.xml`) and an llms.txt entry.
- The editor shows an **SEO checklist and score** after each save.
- Write each article in one language (English or Bangla). A future publish date schedules it.
- Three starter articles are added as **drafts**: review them, add a cover image and publish.
- Cover images: JPG/PNG/WebP up to 4 MB, best at 1200×630.
- Submit `https://payslip.tech/sitemap.xml` once in Google Search Console and Bing Webmaster Tools.

## Third-party code
- qrcode-generator by Kazuhiko Arase — MIT licence (`assets/js/qrcode.js`).
- Fonts: Inter, Plus Jakarta Sans, Hind Siliguri via Google Fonts (SIL Open Font License).
