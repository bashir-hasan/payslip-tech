<?php
declare(strict_types=1);

/**
 * SEO, answer-engine (AEO) and generative-engine (GEO / LLM) helpers.
 * Every public page gets: unique title + description, canonical URL, hreflang EN/BN,
 * Open Graph + Twitter cards and JSON-LD structured data. Admins can override per page.
 */

const SEO_PAGES = ['home' => '/', 'verify' => '/verify', 'developers' => '/developers', 'privacy' => '/privacy', 'terms' => '/terms', 'register' => '/register', 'login' => '/login', 'blog' => '/blog'];

function seo_override(string $page, string $field): ?string
{
    $v = setting('seo_' . $page . '_' . $field . '_' . lang()) ?: setting('seo_' . $page . '_' . $field);
    return $v !== null && trim((string)$v) !== '' ? trim((string)$v) : null;
}

function site_origin(): string
{
    $u = rtrim((string)cfg('app_url', ''), '/');
    return $u !== '' ? $u : (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
}

/** Canonical URL for the current path (no query string; BN version uses ?lang=bn). */
function canonical_url(?string $path = null, ?string $lang = null): string
{
    $path = $path ?? current_path();
    $lang = $lang ?? lang();
    return site_origin() . ($path === '/' ? '/' : $path) . ($lang === 'bn' ? '?lang=bn' : '');
}

function current_path(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $b = base_path();
    if ($b !== '' && str_starts_with($p, $b)) $p = substr($p, strlen($b));
    return '/' . trim($p, '/');
}

function og_image_url(): string
{
    $custom = setting('seo_og_image');
    if ($custom && is_file(ROOT . '/uploads/logos/' . basename($custom))) return site_origin() . '/uploads/logos/' . basename($custom);
    return site_origin() . '/assets/img/og.png';
}

function default_description(): string
{
    return (string)(setting('seo_default_description_' . lang()) ?: setting('seo_default_description') ?: t('Issue tamper-proof, QR-verified payslips and pay stubs in any currency. Banks, landlords, embassies and employers in the US, Canada, Europe and South Asia can confirm one is genuine in a single scan.'));
}

function social_profiles(): array
{
    $lines = preg_split('/\r?\n/', (string)setting('seo_same_as', '')) ?: [];
    return array_values(array_filter(array_map('trim', $lines), fn($l) => filter_var($l, FILTER_VALIDATE_URL)));
}

function jsonld_organization(): array
{
    $org = [
        '@type' => 'Organization',
        '@id' => site_origin() . '/#organization',
        'name' => site_name(),
        'url' => site_origin() . '/',
        'logo' => ['@type' => 'ImageObject', 'url' => site_origin() . '/assets/img/logo-512.png', 'width' => 512, 'height' => 512],
        'description' => 'PaySlip.tech is a payroll and payslip-verification platform. Employers issue payslips and pay stubs with a QR code and digital fingerprint; banks, landlords, embassies and employers verify them in one scan.',
        'areaServed' => array_map(fn($n) => ['@type' => 'Country', 'name' => $n], ['United States', 'Canada', 'United Kingdom', 'European Union', 'Bangladesh', 'India', 'Pakistan', 'Sri Lanka', 'Nepal', 'United Arab Emirates', 'Australia', 'Singapore']),
        'foundingLocation' => ['@type' => 'Place', 'name' => 'Dhaka, Bangladesh'],
    ];
    if ($se = setting('support_email')) $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $se, 'availableLanguage' => ['English', 'Bengali']];
    if ($same = social_profiles()) $org['sameAs'] = $same;
    if ($founder = setting('seo_founder')) $org['founder'] = ['@type' => 'Person', 'name' => $founder];
    return $org;
}

function jsonld_website(): array
{
    return [
        '@type' => 'WebSite',
        '@id' => site_origin() . '/#website',
        'url' => site_origin() . '/',
        'name' => site_name(),
        'inLanguage' => ['en', 'bn'],
        'publisher' => ['@id' => site_origin() . '/#organization'],
    ];
}

function jsonld_software(): array
{
    return [
        '@type' => 'SoftwareApplication',
        '@id' => site_origin() . '/#software',
        'name' => site_name(),
        'url' => site_origin() . '/',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'Payroll and document verification',
        'operatingSystem' => 'Web browser (desktop and mobile)',
        'inLanguage' => ['en', 'bn'],
        'description' => 'Payroll software that issues tamper-proof payslips and pay stubs with a QR code, in 20 currencies with local payslip terms. Anyone can verify a payslip online for free.',
        'featureList' => [
            'Monthly payroll with base pay, allowances, overtime, bonus, tax, retirement and other deductions',
            'Local payslip terms for the US (pay stub, FICA), Canada (CPP/EI), UK (PAYE, NI), EU and South Asia (provident fund, TDS)',
            '20 currencies including USD, CAD, EUR, GBP, BDT, INR and PKR',
            'Payslips in English and Bangla with QR code and digital fingerprint',
            'Free public verification page and JSON verification API',
            'Revoke payslips and see a verification log',
            'Bank, mobile wallet and cash payment sheet export',
            'Two-factor sign-in, audit log and tamper detection',
            'Employee self-service portal',
        ],
        'offers' => (function () {
            $prices = array_map(fn($p) => (float)$p['price'], plans());
            return ['@type' => 'AggregateOffer', 'lowPrice' => (string)min($prices), 'highPrice' => (string)max($prices), 'priceCurrency' => 'USD', 'offerCount' => (string)count($prices)];
        })(),
        'publisher' => ['@id' => site_origin() . '/#organization'],
        'image' => og_image_url(),
    ];
}

function jsonld_breadcrumb(array $items): array
{
    $list = [];
    foreach (array_values($items) as $i => [$name, $path]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => site_origin() . ($path === '/' ? '/' : $path)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function jsonld_faq(array $faqs): array
{
    return [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
    ];
}

function jsonld_script(array $graph): string
{
    $doc = ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
    $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** FAQ content shared by the home page HTML and its FAQPage schema (answer-engine friendly). */
function home_faqs(?string $L = null): array
{
    $L = $L ?? lang();
    return [
        [t('What is PaySlip.tech?', $L), t('PaySlip.tech is an online payroll tool that issues tamper-proof payslips and pay stubs. Each payslip carries a QR code and a digital fingerprint, so a bank, embassy, landlord or new employer can confirm in seconds that it is genuine and unchanged.', $L)],
        [t('How does payslip verification work?', $L), t('When an employer finalises payroll, each payslip gets a unique code and a digital fingerprint calculated from its contents. Scanning the QR code opens our verification page, which re-checks the fingerprint. If anything was changed, or if the employer revoked the payslip, the verifier sees it immediately.', $L)],
        [t('How can a bank or landlord check if a payslip is real?', $L), t('Scan the QR code on a PaySlip.tech payslip or pay stub, or enter its code at payslip.tech/verify. The page shows the employer, employee, month and amounts stored when it was issued. If the paper document shows different figures, it has been altered.', $L)],
        [t('Can someone edit the PDF and still pass verification?', $L), t('No. The verification page shows the original figures stored when the payslip was issued. If the numbers on the paper differ from the numbers on the verification page, the document has been altered.', $L)],
        [t('What does a verifier see?', $L), t('The employer name, employee name and ID, designation, pay period, issue date and, if the employer allows it, gross pay, deductions and net pay. Bank account numbers are never shown.', $L)],
        [t('Which countries and currencies are supported?', $L), t('PaySlip.tech works for teams in the United States, Canada, the UK and Europe, South Asia (Bangladesh, India, Pakistan, Sri Lanka, Nepal) and more. It supports 20 currencies and local payslip terms such as Pay Stub and FICA in the US, CPP and EI in Canada, PAYE and National Insurance in the UK, and Provident Fund and TDS in South Asia. Payslips print in English or Bangla.', $L)],
        [t('How much does it cost?', $L), t('There is a free plan for small teams and low-cost monthly plans for larger teams. Payments are handled by Polar, which calculates sales tax or VAT for your country. Verifying a payslip is always free, with no account needed.', $L)],
        [t('Does it calculate income tax?', $L), t('You enter the monthly tax withholding for each employee, and it appears on every payslip with your local label (for example PAYE, TDS or federal and state tax). Automatic tax calculation is on our roadmap.', $L)],
        [t('Is my data safe?', $L), t('Passwords are hashed, two-factor sign-in is available, every form is protected against forgery, and each company can only see its own data. Bank account numbers are masked on payslips and never shown to verifiers.', $L)],
    ];
}

/** Tracking / custom code configured by the platform admin. */
function tracking_enabled_here(string $context): bool
{
    if ($context === 'public') return true;
    return $context === 'app' && setting('code_in_app', '0') === '1';
}

function has_custom_scripts(): bool
{
    return (bool)(setting('ga4_id') || setting('gtm_id') || setting('fb_pixel_id') || setting('clarity_id') || trim((string)setting('code_head')) || trim((string)setting('code_body')) || trim((string)setting('code_footer')));
}

function head_tracking_code(): string
{
    $out = '';
    if ($v = setting('google_site_verification')) $out .= '<meta name="google-site-verification" content="' . e($v) . '">' . "\n";
    if ($v = setting('bing_site_verification')) $out .= '<meta name="msvalidate.01" content="' . e($v) . '">' . "\n";
    if ($v = setting('fb_domain_verification')) $out .= '<meta name="facebook-domain-verification" content="' . e($v) . '">' . "\n";
    $gtm = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string)setting('gtm_id')));
    if ($gtm) {
        $out .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . $gtm . "');</script>\n";
    }
    $ga = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string)setting('ga4_id')));
    if ($ga) {
        $out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga . '"></script>' . "\n";
        $out .= "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $ga . "',{anonymize_ip:true});</script>\n";
    }
    $clarity = preg_replace('/[^a-z0-9]/', '', strtolower((string)setting('clarity_id')));
    if ($clarity) {
        $out .= '<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","' . $clarity . '");</script>' . "\n";
    }
    $px = preg_replace('/\D/', '', (string)setting('fb_pixel_id'));
    if ($px) {
        $out .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . $px . "');fbq('track','PageView');</script>\n";
    }
    $out .= (string)setting('code_head', '');
    return $out;
}

function body_tracking_code(): string
{
    $out = '';
    $gtm = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string)setting('gtm_id')));
    if ($gtm) $out .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    return $out . (string)setting('code_body', '');
}

function footer_tracking_code(): string
{
    return (string)setting('code_footer', '');
}

/* ---------------- Custom employee fields ---------------- */

/** @return array<int, array{key:string,label:string,label_bn:string,type:string,options:string,on_payslip:bool,required:bool}> */
function custom_fields(): array
{
    $f = json_decode((string)setting('custom_fields', '[]'), true);
    return is_array($f) ? $f : [];
}

function custom_field_label(array $f, ?string $lang = null): string
{
    $lang = $lang ?? lang();
    return $lang === 'bn' && !empty($f['label_bn']) ? $f['label_bn'] : $f['label'];
}
