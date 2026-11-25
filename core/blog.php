<?php
declare(strict_types=1);

/* =====================================================================
 * Blog: safe Markdown, reading time, SEO checks, schema, starter posts
 * ===================================================================== */

function slugify(string $s): string
{
    $s = trim($s);
    if (preg_match('/\p{Bengali}/u', $s)) {
        // Keep Bangla slugs readable: letters, marks, digits and hyphens only
        $s = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', mb_strtolower($s));
    } else {
        if (function_exists('iconv')) $s = (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s));
    }
    $s = trim((string)$s, '-');
    return mb_substr($s !== '' ? $s : 'post', 0, 150);
}

function post_url(array $p): string
{
    return url('/blog/' . rawurlencode($p['slug']));
}

function post_abs_url(array $p): string
{
    return site_origin() . '/blog/' . rawurlencode($p['slug']);
}

function post_cover_url(array $p): ?string
{
    return !empty($p['cover']) ? site_origin() . '/uploads/blog/' . rawurlencode($p['cover']) : null;
}

function reading_minutes(string $md): int
{
    $words = count(preg_split('/\s+/u', trim(strip_tags($md)), -1, PREG_SPLIT_NO_EMPTY));
    return max(1, (int)round($words / 220));
}

function word_count(string $md): int
{
    return count(preg_split('/\s+/u', trim(preg_replace('/[#*_>`|\[\]()!-]+/', ' ', $md)), -1, PREG_SPLIT_NO_EMPTY));
}

/** Parses "Q: ... / A: ..." lines into FAQ pairs. */
function parse_faq(?string $text): array
{
    $out = [];
    $q = null;
    foreach (preg_split('/\R/u', (string)$text) as $line) {
        $line = trim($line);
        if (preg_match('/^(?:Q|প্রশ্ন)\s*[:：]\s*(.+)$/iu', $line, $m)) {
            $q = $m[1];
        } elseif ($q !== null && preg_match('/^(?:A|উত্তর)\s*[:：]\s*(.+)$/iu', $line, $m)) {
            $out[] = [$q, $m[1]];
            $q = null;
        } elseif ($out && $q === null && $line !== '') {
            $out[count($out) - 1][1] .= ' ' . $line; // multi-line answer
        }
    }
    return $out;
}

/** Inline Markdown on already-escaped text. */
function md_inline(string $s): string
{
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x01" . (count($codes) - 1) . "\x01";
    }, $s);
    $safe = function (string $u): ?string {
        $u = html_entity_decode($u, ENT_QUOTES);
        if (preg_match('#^(https?://|/|\#|mailto:)#i', $u) && !preg_match('#^//#', $u)) return e($u);
        return null;
    };
    // Images ![alt](url)
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) use ($safe) {
        $u = $safe($m[2]);
        return $u ? '<img src="' . $u . '" alt="' . $m[1] . '" loading="lazy" decoding="async">' : $m[0];
    }, $s);
    // Links [text](url)
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use ($safe) {
        $u = $safe($m[2]);
        if (!$u) return $m[1];
        $ext = preg_match('#^https?://#i', html_entity_decode($u)) && !str_starts_with(html_entity_decode($u), site_origin());
        return '<a href="' . $u . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
    return preg_replace_callback("/\x01(\d+)\x01/", fn($m) => $codes[(int)$m[1]], $s);
}

/**
 * Converts Markdown to HTML. Raw HTML is escaped, so authors cannot inject scripts.
 * Supports ## headings, paragraphs, lists, > quotes, ``` code, tables, ---, images, links,
 * **bold**, *italic*, `code`, and [[cta]] for a sign-up box.
 * @return array{html: string, toc: array}
 */
function md_render(string $md): array
{
    $lines = preg_split('/\R/u', str_replace("\t", '    ', $md));
    $html = '';
    $toc = [];
    $ids = [];
    $para = [];
    $flush = function () use (&$para, &$html) {
        if ($para) { $html .= '<p>' . md_inline(e(implode(' ', $para))) . "</p>\n"; $para = []; }
    };
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = rtrim($lines[$i]);
        $t = ltrim($line);
        if ($t === '') { $flush(); continue; }
        if (str_starts_with($t, '```')) {
            $flush();
            $code = [];
            for ($i++; $i < $n && !str_starts_with(ltrim($lines[$i]), '```'); $i++) $code[] = $lines[$i];
            $html .= '<pre class="code"><code>' . e(implode("\n", $code)) . "</code></pre>\n";
            continue;
        }
        if ($t === '[[cta]]') { $flush(); $html .= "<!--cta-->\n"; continue; }
        if (preg_match('/^(-{3,}|\*{3,})$/', $t)) { $flush(); $html .= "<hr>\n"; continue; }
        if (preg_match('/^(#{2,4})\s+(.+?)\s*#*$/u', $t, $m)) {
            $flush();
            $lvl = strlen($m[1]);
            $text = $m[2];
            $id = slugify(strip_tags($text));
            $base = $id; $k = 2;
            while (isset($ids[$id])) $id = $base . '-' . $k++;
            $ids[$id] = 1;
            if ($lvl === 2) $toc[] = [$id, $text];
            $html .= "<h$lvl id=\"" . e($id) . '">' . md_inline(e($text)) . "</h$lvl>\n";
            continue;
        }
        if (preg_match('/^#\s+(.+)$/u', $t, $m)) { $flush(); $html .= '<h2>' . md_inline(e($m[1])) . "</h2>\n"; continue; }
        if (str_starts_with($t, '>')) {
            $flush();
            $q = [];
            for (; $i < $n && str_starts_with(ltrim($lines[$i]), '>'); $i++) $q[] = ltrim(substr(ltrim($lines[$i]), 1));
            $i--;
            $html .= '<blockquote><p>' . md_inline(e(implode(' ', $q))) . "</p></blockquote>\n";
            continue;
        }
        if (preg_match('/^([-*+]|\d+[.)])\s+/', $t)) {
            $flush();
            $ordered = (bool)preg_match('/^\d/', $t);
            $items = [];
            for (; $i < $n; $i++) {
                $l = ltrim($lines[$i]);
                if (preg_match('/^([-*+]|\d+[.)])\s+(.*)$/', $l, $mm)) $items[] = $mm[2];
                elseif ($l !== '' && $items && preg_match('/^\s{2,}/', $lines[$i])) $items[count($items) - 1] .= ' ' . $l;
                else break;
            }
            $i--;
            $tag = $ordered ? 'ol' : 'ul';
            $html .= "<$tag>" . implode('', array_map(fn($x) => '<li>' . md_inline(e($x)) . '</li>', $items)) . "</$tag>\n";
            continue;
        }
        if (str_starts_with($t, '|') && $i + 1 < $n && preg_match('/^\|?\s*:?-{2,}/', ltrim($lines[$i + 1]))) {
            $flush();
            $cells = fn($r) => array_map('trim', explode('|', trim(trim($r), '|')));
            $head = $cells($t);
            $i += 2;
            $rows = [];
            for (; $i < $n && str_starts_with(ltrim($lines[$i]), '|'); $i++) $rows[] = $cells($lines[$i]);
            $i--;
            $html .= '<div class="table-wrap"><table class="table"><thead><tr>' . implode('', array_map(fn($c) => '<th>' . md_inline(e($c)) . '</th>', $head)) . '</tr></thead><tbody>';
            foreach ($rows as $r) $html .= '<tr>' . implode('', array_map(fn($c) => '<td>' . md_inline(e($c)) . '</td>', $r)) . '</tr>';
            $html .= "</tbody></table></div>\n";
            continue;
        }
        $para[] = $t;
    }
    $flush();
    return ['html' => $html, 'toc' => $toc];
}

/** Simple on-page SEO checklist shown in the editor. @return array<int, array{0: bool, 1: string}> */
function post_seo_checks(array $p): array
{
    $title = (string)($p['meta_title'] ?: $p['title']);
    $desc = (string)($p['meta_description'] ?: $p['excerpt']);
    $kw = mb_strtolower(trim((string)$p['keyword']));
    $body = (string)$p['body'];
    $words = word_count($body);
    $first = mb_strtolower(mb_substr(trim(preg_replace('/^#.*$/m', '', $body)), 0, 400));
    $tl = mb_strlen($title);
    $dl = mb_strlen($desc);
    $c = [];
    $c[] = [$tl >= 30 && $tl <= 65, sprintf(t('SEO title length %d characters (best 30–65).'), $tl)];
    $c[] = [$dl >= 80 && $dl <= 160, sprintf(t('Meta description length %d characters (best 80–160).'), $dl)];
    $c[] = [$words >= 600, sprintf(t('%d words (aim for 600+ for guides).'), $words)];
    $c[] = [preg_match_all('/^##\s/m', $body) >= 2, t('At least two ## section headings.')];
    $c[] = [(bool)preg_match('/\]\((\/|' . preg_quote(site_origin(), '/') . ')/', $body), t('Links to another page on this site (e.g. /verify or /register).')];
    $c[] = [count(parse_faq($p['faq'] ?? '')) >= 2, t('At least two FAQ questions (shown as rich results).')];
    $c[] = [!empty($p['cover']), t('Cover image added (used for social sharing).')];
    if ($kw !== '') {
        $c[] = [str_contains(mb_strtolower($title), $kw), t('Focus keyword appears in the SEO title.')];
        $c[] = [str_contains($first, $kw), t('Focus keyword appears in the first paragraph.')];
        $c[] = [str_contains(mb_strtolower($desc), $kw), t('Focus keyword appears in the meta description.')];
    } else {
        $c[] = [false, t('Set a focus keyword (the main phrase people search for).')];
    }
    return $c;
}

function jsonld_blogposting(array $p, array $faqs): array
{
    $author = $p['author_name'] ?? null;
    $node = [
        '@type' => 'BlogPosting',
        '@id' => post_abs_url($p) . '#article',
        'headline' => mb_substr($p['title'], 0, 110),
        'description' => $p['meta_description'] ?: $p['excerpt'],
        'url' => post_abs_url($p),
        'mainEntityOfPage' => post_abs_url($p),
        'inLanguage' => $p['lang'] === 'bn' ? 'bn-BD' : 'en',
        'datePublished' => date('c', strtotime((string)($p['published_at'] ?: $p['created_at']))),
        'dateModified' => date('c', strtotime((string)$p['updated_at'])),
        'wordCount' => word_count($p['body']),
        'author' => $author ? ['@type' => 'Person', 'name' => $author, 'url' => site_origin() . '/'] : ['@id' => site_origin() . '/#organization'],
        'publisher' => ['@id' => site_origin() . '/#organization'],
        'isPartOf' => ['@type' => 'Blog', '@id' => site_origin() . '/blog#blog', 'name' => site_name() . ' Blog', 'url' => site_origin() . '/blog'],
        'image' => post_cover_url($p) ?? og_image_url(),
    ];
    if (!empty($p['keyword'])) $node['keywords'] = $p['keyword'];
    if (!empty($p['category'])) $node['articleSection'] = $p['category'];
    return $node;
}

/* ---------------- Starter articles (saved as drafts to review) ---------------- */

function seed_blog_posts(): void
{
    $now = now();
    foreach (blog_starter_posts() as $p) {
        $slug = $p['slug'];
        if (db_val('SELECT 1 FROM posts WHERE slug = ?', [$slug])) continue;
        db_insert('posts', $p + ['status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
    }
}

function blog_starter_posts(): array
{
    return [
        [
            'slug' => 'how-to-check-if-a-payslip-is-real',
            'lang' => 'en',
            'category' => 'Verification',
            'keyword' => 'check if a payslip is real',
            'title' => 'How to Check if a Payslip Is Real: A Guide for Banks, Landlords and Employers',
            'meta_title' => 'How to Check if a Payslip Is Real (5 Checks + QR Verification)',
            'meta_description' => 'Learn how to check if a payslip is real: the warning signs of a fake payslip, five manual checks, and how QR-verified payslips confirm income in seconds.',
            'excerpt' => 'Fake payslips are easy to make and hard to spot. Here are the warning signs, five checks you can do today, and how verified payslips remove the guesswork.',
            'body' => <<<'MD'
If you approve loans, rent out property, process visas or hire people, you have probably been handed a payslip and wondered whether it was genuine. Knowing how to check if a payslip is real matters, because a payslip is often the only proof of income you get, and editing a PDF or a spreadsheet takes a few minutes.

This guide covers the most common signs of a fake payslip, five checks you can do yourself, and a faster option: payslips that can be verified with a QR code.

## Why fake payslips are a growing problem

Payslip templates are freely available online, and anyone with a phone can change a number in a PDF. Most fakes are not crude forgeries. They are real payslips with one small change: a higher basic salary, a different employer name or an extra allowance. That is exactly why they get through.

The cost lands on whoever relies on the document: a lender with a bad loan, a landlord with unpaid rent, or an employer who set a salary based on an inflated previous offer.

## Warning signs of a fake payslip

None of these prove a payslip is fake on their own, but each is a reason to look closer:

- **Round numbers everywhere.** Real payroll usually produces uneven figures once tax, provident fund or unpaid days are deducted.
- **The maths does not add up.** Earnings minus deductions should equal net pay exactly.
- **Missing details.** No employee ID, no pay period, no employer address or no deductions at all.
- **Inconsistent fonts or alignment**, often a sign that one figure was edited.
- **Net pay that does not match bank statements.** This is the strongest signal of all.
- **The employer cannot be found** online, or the phone number on the payslip goes to the applicant.

## Five checks you can do yourself

1. **Recalculate the totals.** Add up the earnings and deductions and check the net pay.
2. **Compare with bank statements.** Ask for three months of statements and match the salary credits to the net pay on each payslip.
3. **Compare several months.** Genuine payslips change in small, explainable ways (overtime, bonus, leave). Copies with only the month changed are a red flag.
4. **Contact the employer independently.** Find the company's number yourself, not from the payslip, and ask HR to confirm employment and salary.
5. **Check the PDF properties.** The document's creator and modification date can show that it was edited recently in a design or PDF editing tool.

These checks work, but they are slow. Calling HR can take days, and many employers will not confirm salaries over the phone.

## The faster way: verified payslips with a QR code

A verified payslip carries a unique code and a QR code. When you scan it, you see the payslip exactly as the employer issued it, straight from the issuing system. If anyone changed a figure, the numbers will not match.

With [PaySlip.tech](/), each payslip is protected with a cryptographic signature. Scanning the QR code or entering the code at [payslip.tech/verify](/verify) shows one of four results:

| Result | What it means |
|---|---|
| Genuine | The payslip was issued by the employer and has not been changed. |
| Revoked | The employer cancelled this payslip, usually to issue a corrected one. |
| Altered | The data no longer matches the signature. Do not accept it. |
| Not found | No payslip exists with that code. |

Verification is free, takes a few seconds and needs no account. Banks and agencies can also check payslips automatically with the [verification API](/developers).

## What to ask applicants for

If a payslip does not have a QR code, ask for:

- The last three payslips, plus the matching bank statements
- An employment letter on company letterhead
- HR contact details you can confirm independently

If the employer uses verified payslips, one scan replaces most of this.

[[cta]]

## Key takeaways

- Most fake payslips are real documents with one edited number.
- Always recalculate totals and match net pay against bank statements.
- QR-verified payslips let you confirm income in seconds, without calling the employer.
MD,
            'faq' => "Q: How can I tell if a payslip is fake?\nA: Recalculate the totals, compare net pay with bank statement credits, look for missing details such as an employee ID or pay period, and contact the employer using a phone number you found yourself. If the payslip has a QR code, scan it to see the original issued version.\nQ: Can a bank verify a payslip?\nA: Yes. Banks usually match payslips against salary credits in bank statements and may call the employer. Payslips issued on PaySlip.tech can be verified instantly by scanning the QR code or through the free verification API.\nQ: Is it free to verify a PaySlip.tech payslip?\nA: Yes. Anyone can verify a payslip for free at payslip.tech/verify without creating an account.",
        ],
        [
            'slug' => 'what-should-a-payslip-include',
            'lang' => 'en',
            'category' => 'Payroll basics',
            'keyword' => 'what should a payslip include',
            'title' => 'What Should a Payslip Include? A Simple Checklist for Employers',
            'meta_title' => 'What Should a Payslip Include? Employer Checklist',
            'meta_description' => 'What should a payslip include? A clear checklist of employer, employee, earnings and deduction details, with an example layout and tips for small businesses.',
            'excerpt' => 'A clear payslip saves you questions from staff and helps them prove their income. Here is what every payslip should include, with an example layout.',
            'body' => <<<'MD'
A payslip does two jobs. It tells your employee exactly how their pay was calculated, and it becomes their proof of income when they apply for a loan, a flat or a visa. So what should a payslip include? Below is a practical checklist that works for most small and medium businesses.

Rules on what a payslip must show differ by country, so check your local labour and tax regulations as well.

## Employer details

- Registered company name
- Company address
- Logo (optional, but it makes the payslip look official)
- Contact email or phone for payroll questions

## Employee details

- Full name
- Employee ID
- Designation and department
- Joining date
- Payment method (bank transfer, mobile banking or cash) and, if relevant, the last digits of the account

## Pay period and date

- The month or period covered, for example "November 2026"
- The payment date
- Working days in the period and any unpaid days

## Earnings

List each part of the gross pay on its own line. Common items are:

- Basic salary
- House rent or housing allowance
- Medical allowance
- Transport or conveyance
- Overtime
- Bonus or incentive

## Deductions

- Income tax withheld
- Provident fund or pension contributions
- Loan or salary advance repayments
- Deductions for unpaid leave
- Any other agreed deduction, clearly named

## Totals

Every payslip should end with three clear figures: **gross pay**, **total deductions** and **net pay**. Many employers also write net pay in words, which makes later edits harder.

## Example layout

| Earnings | Amount | Deductions | Amount |
|---|---|---|---|
| Basic salary | 30,000 | Income tax | 500 |
| House rent | 15,000 | Provident fund | 1,500 |
| Medical | 2,500 | | |
| Transport | 2,500 | | |
| **Gross pay** | **50,000** | **Total deductions** | **2,000** |

Net pay: **48,000**

## Make it verifiable

A payslip is only useful as proof of income if the person receiving it can trust it. Adding a unique code and a QR code lets a bank or landlord [verify the payslip](/verify) in seconds, and protects your company's name from being used on edited copies.

[PaySlip.tech](/) creates payslips with all of the details above, local terms for 20 currencies, and a verification QR code on every slip. It is free for teams of up to five employees.

[[cta]]

## Quick checklist

- Employer and employee details
- Pay period, payment date and working days
- Every earning and every deduction on its own line
- Gross pay, total deductions and net pay
- A unique code or QR code so it can be verified
MD,
            'faq' => "Q: What should a payslip include?\nA: Employer and employee details, the pay period and payment date, each earning and deduction on its own line, and the gross pay, total deductions and net pay. A verification code or QR code makes it trustworthy as proof of income.\nQ: Do I have to give employees a payslip?\nA: In many countries employers are required to provide a payslip or pay statement. Rules differ by country, so check your local labour regulations.\nQ: Can I make payslips for free?\nA: Yes. PaySlip.tech is free for teams of up to five employees, including QR verification.",
        ],
        [
            'slug' => 'বেতন-স্লিপ-যাচাই',
            'lang' => 'bn',
            'category' => 'যাচাই',
            'keyword' => 'বেতন স্লিপ যাচাই',
            'title' => 'বেতন স্লিপ (পে-স্লিপ) আসল কিনা কীভাবে যাচাই করবেন',
            'meta_title' => 'বেতন স্লিপ আসল কিনা যাচাই করার সহজ উপায়',
            'meta_description' => 'বেতন স্লিপ যাচাই করার সহজ উপায়: নকল পে-স্লিপের লক্ষণ, নিজে করার মতো পাঁচটি পরীক্ষা, আর QR কোড দিয়ে কয়েক সেকেন্ডে যাচাই।',
            'excerpt' => 'লোন, বাসা ভাড়া বা ভিসার জন্য বেতন স্লিপ লাগে। কিন্তু স্লিপটি আসল কিনা বুঝবেন কীভাবে? এখানে সহজ ভাষায় পুরো পদ্ধতি।',
            'body' => <<<'MD'
ব্যাংক লোন, বাসা ভাড়া, ভিসা বা নতুন চাকরি, সব জায়গাতেই আয়ের প্রমাণ হিসেবে বেতন স্লিপ চাওয়া হয়। কিন্তু একটি PDF বা এক্সেল ফাইলে সংখ্যা বদলাতে কয়েক মিনিটই যথেষ্ট। তাই বেতন স্লিপ যাচাই করা এখন খুব জরুরি।

এই লেখায় থাকছে নকল পে-স্লিপ চেনার লক্ষণ, নিজে করার মতো পাঁচটি পরীক্ষা, আর QR কোড দিয়ে কয়েক সেকেন্ডে যাচাইয়ের উপায়।

## নকল বেতন স্লিপের সাধারণ লক্ষণ

- **সব সংখ্যা গোল**: আসল বেতনে কর, পিএফ বা ছুটির কর্তনের পর সাধারণত ভাঙতি সংখ্যা আসে।
- **হিসাব মেলে না**: মোট আয় থেকে মোট কর্তন বাদ দিলে নিট বেতন হুবহু মিলতে হবে।
- **তথ্য অসম্পূর্ণ**: কর্মী আইডি, বেতনের মাস বা প্রতিষ্ঠানের ঠিকানা নেই।
- **ফন্ট বা লেখার লাইন অসমান**, যা প্রায়ই কোনো সংখ্যা এডিট করার চিহ্ন।
- **ব্যাংক স্টেটমেন্টের সাথে মেলে না**: এটাই সবচেয়ে বড় সতর্ক সংকেত।

## নিজে করার মতো পাঁচটি পরীক্ষা

1. আয় ও কর্তন যোগ করে নিট বেতন আবার হিসাব করুন।
2. তিন মাসের ব্যাংক স্টেটমেন্ট চেয়ে বেতন জমার অঙ্কের সাথে মিলিয়ে দেখুন।
3. কয়েক মাসের স্লিপ পাশাপাশি রাখুন। শুধু মাসের নাম বদলানো কপি সন্দেহজনক।
4. প্রতিষ্ঠানের ফোন নম্বর নিজে খুঁজে HR-এর সাথে কথা বলুন, স্লিপে দেওয়া নম্বরে নয়।
5. PDF-এর প্রপার্টিজ দেখুন। সম্প্রতি কোনো এডিটিং সফটওয়্যারে বদলানো হয়েছে কিনা বোঝা যায়।

## সবচেয়ে সহজ উপায়: QR কোডওয়ালা বেতন স্লিপ

যাচাইযোগ্য বেতন স্লিপে একটি ইউনিক কোড ও QR কোড থাকে। ফোনের ক্যামেরা দিয়ে স্ক্যান করলেই নিয়োগকর্তা যে স্লিপটি ইস্যু করেছেন সেটি হুবহু দেখা যায়। কেউ কোনো সংখ্যা বদলালে তা আর মিলবে না।

[PaySlip.tech](/)-এ প্রতিটি স্লিপ ক্রিপ্টোগ্রাফিক সিগনেচার দিয়ে সুরক্ষিত। QR স্ক্যান করলে বা [যাচাই পেজে](/verify) কোডটি লিখলে চারটি ফলাফলের একটি দেখাবে:

| ফলাফল | অর্থ |
|---|---|
| আসল | নিয়োগকর্তা ইস্যু করেছেন, কোনো পরিবর্তন হয়নি। |
| বাতিল | নিয়োগকর্তা স্লিপটি বাতিল করেছেন। |
| পরিবর্তিত | তথ্য বদলানো হয়েছে। গ্রহণ করবেন না। |
| পাওয়া যায়নি | এই কোডে কোনো স্লিপ নেই। |

যাচাই সম্পূর্ণ ফ্রি, কয়েক সেকেন্ড লাগে, কোনো অ্যাকাউন্ট লাগে না।

[[cta]]

## মূল কথা

- বেশিরভাগ নকল স্লিপ আসলে আসল স্লিপেরই একটি সংখ্যা বদলানো কপি।
- সবসময় হিসাব মিলিয়ে দেখুন এবং ব্যাংক স্টেটমেন্টের সাথে তুলনা করুন।
- QR কোডওয়ালা স্লিপ থাকলে এক স্ক্যানেই যাচাই হয়ে যায়।
MD,
            'faq' => "প্রশ্ন: বেতন স্লিপ আসল কিনা কীভাবে বুঝব?\nউত্তর: হিসাব আবার মিলিয়ে দেখুন, ব্যাংক স্টেটমেন্টের বেতন জমার সাথে তুলনা করুন এবং নিজে নম্বর খুঁজে প্রতিষ্ঠানের HR-এর সাথে কথা বলুন। স্লিপে QR কোড থাকলে স্ক্যান করলেই আসল কপি দেখা যাবে।\nপ্রশ্ন: বেতন স্লিপ যাচাই করতে কি টাকা লাগে?\nউত্তর: না। payslip.tech/verify-এ যে কেউ বিনা খরচে, কোনো অ্যাকাউন্ট ছাড়াই যাচাই করতে পারেন।\nপ্রশ্ন: ব্যাংক কি বেতন স্লিপ যাচাই করে?\nউত্তর: হ্যাঁ। ব্যাংক সাধারণত স্টেটমেন্টের সাথে মিলিয়ে দেখে এবং প্রয়োজনে নিয়োগকর্তাকে ফোন করে। PaySlip.tech-এর স্লিপ QR কোড বা ফ্রি API দিয়ে সাথে সাথে যাচাই করা যায়।",
        ],
    ];
}
