<?php
declare(strict_types=1);

/**
 * Support chatbot: answers from a trainable knowledge base (Admin → Chat → Knowledge base).
 * No external AI service: matching uses keywords, synonyms, Bangla/English normalisation and fuzzy scoring.
 * Unanswered questions are collected so the admin can add answers ("train" the bot).
 */

function kb_seed_entries(): array
{
    $e = [
        ['Can I pay with bKash, Nagad or a local card?', 'Paid plans are billed through Polar, which accepts international debit and credit cards (Visa, Mastercard, Amex) and Apple Pay / Google Pay. bKash and Nagad are not supported yet. Most Bangladeshi bank debit cards with international payments switched on work. You can stay on the Free plan as long as you like.', 'bkash nagad rocket mobile banking local card payment method visa mastercard pay', 'en', '/#pricing'],
        ['বিকাশ বা নগদ দিয়ে পেমেন্ট করা যাবে?', 'পেইড প্ল্যানের পেমেন্ট Polar-এর মাধ্যমে হয়: আন্তর্জাতিক ডেবিট/ক্রেডিট কার্ড (Visa, Mastercard) এবং Apple Pay / Google Pay। বিকাশ ও নগদ এখনো সাপোর্ট করে না। আন্তর্জাতিক লেনদেন চালু থাকা বেশিরভাগ বাংলাদেশি ব্যাংক কার্ড কাজ করে।', 'বিকাশ নগদ রকেট কার্ড পেমেন্ট', 'bn', '/#pricing'],
        ['Can payroll run automatically every month?', 'Yes. Turn on Auto payroll: a draft is prepared a few days before pay day, you get an email to add bonuses or unpaid days, and on pay day the payslips are issued and emailed to employees automatically.', 'automatic auto monthly schedule recurring every month automation', 'en', '/app/automation'],
        ['What can the smart assistant do?', 'Type plain commands like "Rahim bonus 5000, Sadia 2 unpaid days" or "finalise and email payslips" in English or Bangla. It shows a preview first and only changes anything when you click Apply.', 'assistant command bot ai smart type chat automate', 'en', '/app/assistant'],
        // [question, answer, keywords, lang, link]
        ['What is PaySlip.tech?', 'PaySlip.tech is an online payroll tool that issues tamper-proof payslips and pay stubs. Each payslip has a QR code and a digital fingerprint, so banks, landlords, embassies and employers can confirm it is genuine in seconds.', 'what is about product service explain overview', 'en', '/'],
        ['How do I verify a payslip?', 'Scan the QR code on the payslip with your phone camera, or enter the code (PS-XXXX-XXXX) at payslip.tech/verify. It is free and needs no account.', 'verify check real genuine fake authentic qr scan code validate', 'en', '/verify'],
        ['The verify page says "No payslip found". What does it mean?', 'There is no issued payslip with that code. Check the code for typing mistakes (it looks like PS-XXXX-XXXX). If it is correct, the document is not a genuine PaySlip.tech payslip.', 'not found invalid no payslip found wrong code error', 'en', '/verify'],
        ['What does "Revoked" mean?', 'The employer issued the payslip but later withdrew it, for example because it had a mistake. Do not accept a revoked payslip; ask the employee for the corrected one.', 'revoked revoke cancelled withdrawn', 'en', null],
        ['What does "Integrity check failed" mean?', 'The stored payslip data no longer matches its digital fingerprint, which means it may have been tampered with. Do not accept it and contact the employer.', 'tampered integrity failed fingerprint changed altered', 'en', null],
        ['How much does it cost?', 'There is a Free plan (up to 5 employees) and paid Starter and Business plans for larger teams. Verifying payslips is always free. See the pricing section for current prices.', 'price pricing cost plan plans fee subscription free paid how much charge', 'en', '/#pricing'],
        ['How do I pay or upgrade my plan?', 'Sign in, open Plan & billing, and click Upgrade. Payment is handled securely by Polar (cards and other local methods). Sales tax or VAT is calculated at checkout.', 'pay upgrade payment card checkout polar billing buy', 'en', '/app/billing'],
        ['How do I cancel my subscription or get invoices?', 'Open Plan & billing and click "Manage subscription & invoices". You can cancel anytime; your plan stays active until the end of the paid period.', 'cancel subscription invoice receipt refund downgrade', 'en', '/app/billing'],
        ['Do you offer refunds?', 'Payments are processed by Polar, our merchant of record. If something went wrong with a charge, contact us and we will help, including refunds where appropriate.', 'refund money back chargeback', 'en', null],
        ['Which countries and currencies are supported?', 'Teams in the US, Canada, UK and Europe, South Asia (Bangladesh, India, Pakistan, Sri Lanka, Nepal), the Gulf, Australia and more. 20 currencies are supported with local payslip terms such as Pay Stub and FICA (US), CPP/EI (Canada), PAYE and NI (UK) and Provident Fund/TDS (South Asia).', 'country countries currency currencies usa us canada uk europe india pakistan bangladesh dollar euro pound rupee taka worldwide international', 'en', null],
        ['Can I issue payslips in Bangla?', 'Yes. Payslips can be viewed and printed in English or Bangla, and anyone viewing a payslip can switch language.', 'bangla bengali language english translate', 'en', null],
        ['How do I get started?', 'Create a free account, complete your company profile, add employees (or import a CSV/Excel file), then create a payroll run and click Finalise to issue verified payslips.', 'start started begin setup sign up register create account how to use', 'en', '/register'],
        ['How do I add employees?', 'Go to Employees and click Add employee, or use Import to upload a CSV or Excel file. The smart import recognises most column names automatically.', 'add employee employees staff import csv excel upload team', 'en', '/app/employees'],
        ['How do I run payroll?', 'Open Payroll, click New payroll run, choose the month, adjust bonus/overtime/advances/unpaid days if needed, then click Finalise & issue. Each payslip gets its own QR code.', 'run payroll monthly salary process create finalise finalize issue', 'en', '/app/payroll'],
        ['Can payroll run automatically every month?', 'Yes. Turn on Auto payroll and choose a pay day. A draft is prepared a few days earlier for you to review, then payslips are issued and emailed automatically on pay day.', 'automatic auto automation schedule every month recurring', 'en', '/app/automation'],
        ['What is the smart assistant?', 'In your dashboard, type commands like "Rahim bonus 5000, Sadia 2 unpaid days, finalise November payroll". The assistant shows a preview and applies it after you confirm.', 'assistant smart ai bot command chat automate', 'en', '/app/assistant'],
        ['How do employees get their payslips?', 'Invite employees from the Employees page. They set a password and can view, download (PDF) and share their payslips. You can also email payslips from the payroll page.', 'employee portal access login download pdf share invite', 'en', null],
        ['I did not receive the invitation or email', 'Check your spam folder. Ask your employer to resend the invitation; they can also copy the invitation link and send it by WhatsApp or SMS.', 'email not received invitation spam missing mail', 'en', null],
        ['I forgot my password', 'Click "Forgot password?" on the sign-in page and enter your email. You will receive a reset link valid for 1 hour.', 'forgot password reset login cannot sign in locked', 'en', '/forgot'],
        ['My account is locked', 'After 5 wrong passwords an account is locked for 15 minutes for security. Wait and try again, or reset your password.', 'locked lock blocked too many attempts', 'en', '/forgot'],
        ['How do I turn on two-factor authentication?', 'Open My account → Two-factor authentication → Set up, scan the QR code with Google Authenticator, Microsoft Authenticator or Authy, and save your recovery codes.', '2fa two factor authentication authenticator security otp code', 'en', '/account'],
        ['Is my data safe?', 'Yes. Passwords are hashed, two-factor sign-in is available, every form is protected, data is encrypted in transit, and each company only sees its own data. Bank account numbers are masked and never shown to verifiers.', 'safe secure security privacy data protection hack gdpr', 'en', '/privacy'],
        ['What does a verifier see?', 'Employer name, employee name and ID, designation, pay month, issue date and, if the employer allows it, gross pay, deductions and net pay. Bank account numbers are never shown.', 'verifier see show visible information privacy bank', 'en', null],
        ['Is there an API for banks?', 'Yes. GET /api/v1/verify/{CODE} returns JSON with the result and the original figures. It is free and needs no key.', 'api developer integration bank json automate check bulk', 'en', '/developers'],
        ['Does it calculate income tax?', 'You enter each employee\'s monthly tax withholding and it appears on every payslip with your local label (TDS, PAYE, federal and state tax...). Automatic tax calculation is on our roadmap.', 'tax income tax calculate tds paye withholding', 'en', null],
        ['Can I change the names of payslip lines?', 'Yes. Settings → Payslip line names lets you rename every earning and deduction, or reset to your country\'s defaults.', 'rename label line names allowance deduction custom', 'en', '/app/settings'],
        ['Can I add extra employee fields?', 'Yes. Extra fields such as branch, grade or blood group can be added and optionally printed on payslips.', 'custom field extra information blood group branch nid', 'en', null],
        ['How do I contact a human?', 'Type "talk to a human" here, or tap the WhatsApp button to chat with our team directly.', 'human agent person support contact talk call phone whatsapp help team', 'en', null],
        // Bangla
        ['PaySlip.tech কী?', 'PaySlip.tech একটি অনলাইন বেতন প্রক্রিয়া টুল, যা QR কোড ও ডিজিটাল ফিঙ্গারপ্রিন্টসহ অপরিবর্তনীয় বেতন স্লিপ দেয়। ব্যাংক, দূতাবাস ও বাড়িওয়ালা কয়েক সেকেন্ডে যাচাই করতে পারেন।', 'কী কি সম্পর্কে পরিচিতি', 'bn', '/'],
        ['বেতন স্লিপ কীভাবে যাচাই করব?', 'ফোনের ক্যামেরা দিয়ে স্লিপের QR কোড স্ক্যান করুন, অথবা payslip.tech/verify-এ কোডটি (PS-XXXX-XXXX) লিখুন। এটি ফ্রি, অ্যাকাউন্ট লাগে না।', 'যাচাই ভেরিফাই আসল নকল জাল কিউআর স্ক্যান কোড', 'bn', '/verify'],
        ['খরচ কত?', 'ফ্রি প্ল্যানে ৫ জন কর্মী পর্যন্ত। বড় টিমের জন্য Starter ও Business প্ল্যান আছে। স্লিপ যাচাই সবসময় ফ্রি।', 'খরচ দাম মূল্য টাকা প্ল্যান ফ্রি সাবস্ক্রিপশন', 'bn', '/#pricing'],
        ['কীভাবে শুরু করব?', 'ফ্রি অ্যাকাউন্ট খুলুন, প্রতিষ্ঠানের তথ্য দিন, কর্মী যোগ করুন (বা Excel/CSV ইমপোর্ট করুন), তারপর বেতন প্রক্রিয়া চালিয়ে চূড়ান্ত করুন।', 'শুরু রেজিস্টার অ্যাকাউন্ট খুলব কিভাবে ব্যবহার', 'bn', '/register'],
        ['কর্মী কীভাবে যোগ করব?', 'কর্মী পেজে "কর্মী যোগ করুন" চাপুন, অথবা Excel/CSV ফাইল ইমপোর্ট করুন।', 'কর্মী যোগ এমপ্লয়ি ইমপোর্ট এক্সেল', 'bn', '/app/employees'],
        ['প্রতি মাসে স্বয়ংক্রিয়ভাবে বেতন হবে?', 'হ্যাঁ। অটো পেরোল চালু করে বেতনের তারিখ দিন। কয়েক দিন আগে খসড়া তৈরি হবে, আর বেতনের দিনে স্লিপ ইস্যু ও ইমেইল হয়ে যাবে।', 'স্বয়ংক্রিয় অটো প্রতি মাসে অটোমেটিক', 'bn', '/app/automation'],
        ['বিকাশ বা নগদে বেতন দেওয়া যায়?', 'হ্যাঁ। কর্মীর পরিশোধ মাধ্যম হিসেবে বিকাশ, নগদ, রকেট, ব্যাংক বা নগদ অর্থ বেছে নিন এবং পেমেন্ট শিট ডাউনলোড করুন।', 'বিকাশ নগদ রকেট মোবাইল ব্যাংকিং পেমেন্ট', 'bn', null],
        ['পাসওয়ার্ড ভুলে গেছি', 'সাইন ইন পেজে "পাসওয়ার্ড ভুলে গেছেন?" চাপুন ও ইমেইল দিন। ১ ঘণ্টার জন্য বৈধ রিসেট লিংক পাবেন।', 'পাসওয়ার্ড ভুলে রিসেট লগইন', 'bn', '/forgot'],
        ['আমার তথ্য কি নিরাপদ?', 'হ্যাঁ। পাসওয়ার্ড হ্যাশ করা থাকে, টু-ফ্যাক্টর সাইন ইন আছে, আর প্রতিটি প্রতিষ্ঠান শুধু নিজের তথ্য দেখে। ব্যাংক অ্যাকাউন্ট নম্বর যাচাইকারীকে দেখানো হয় না।', 'নিরাপদ নিরাপত্তা তথ্য গোপনীয়তা হ্যাক', 'bn', '/privacy'],
        ['মানুষের সাথে কথা বলতে চাই', '"মানুষের সাথে কথা" লিখুন বা WhatsApp বাটনে চাপ দিয়ে সরাসরি আমাদের টিমের সাথে কথা বলুন।', 'মানুষ এজেন্ট সাপোর্ট যোগাযোগ কথা হোয়াটসঅ্যাপ ফোন সাহায্য', 'bn', null],
    ];
    return $e;
}

function seed_knowledge_base(): void
{
    foreach (kb_seed_entries() as [$q, $a, $k, $l, $link]) {
        db_insert('kb_entries', ['question' => $q, 'answer' => $a, 'keywords' => $k, 'lang' => $l, 'link' => $link, 'created_at' => now()]);
    }
}

/* ---------------- Matching ---------------- */

function kb_normalize(string $s): string
{
    $s = mb_strtolower(bn_to_en_digits($s));
    $s = preg_replace('/[^\p{L}\p{M}\p{N}\s-]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function kb_tokens(string $s): array
{
    static $stop = ['the', 'a', 'an', 'is', 'are', 'i', 'my', 'me', 'we', 'you', 'your', 'to', 'of', 'in', 'on', 'for', 'and', 'or', 'do', 'does', 'can', 'how', 'what', 'it', 'this', 'that', 'be', 'with', 'please', 'pls', 'hi', 'hello', 'there', 'want', 'need', 'would', 'like', 'will', 'should', 'have', 'has', 'get', 'any', 'about', 'আমি', 'আমার', 'কি', 'কী', 'কিভাবে', 'কীভাবে', 'এর', 'এই', 'না', 'হবে', 'করব', 'করতে', 'আছে', 'যায়', 'দিয়ে', 'জন্য'];
    $syn = ['cost' => 'price', 'prices' => 'price', 'pricing' => 'price', 'charges' => 'price', 'fee' => 'price', 'fees' => 'price', 'paystub' => 'payslip', 'stub' => 'payslip', 'slip' => 'payslip', 'slips' => 'payslip', 'payslips' => 'payslip', 'salary' => 'payroll', 'wages' => 'payroll', 'staff' => 'employee', 'employees' => 'employee', 'workers' => 'employee', 'fake' => 'verify', 'genuine' => 'verify', 'real' => 'verify', 'check' => 'verify', 'authentic' => 'verify', 'automatic' => 'auto', 'automatically' => 'auto', 'automation' => 'auto', 'login' => 'sign', 'signin' => 'sign', 'refunds' => 'refund', 'countries' => 'country', 'currencies' => 'currency', 'agent' => 'human', 'person' => 'human', 'বেতন' => 'payroll', 'স্লিপ' => 'payslip', 'যাচাই' => 'verify'];
    $out = [];
    foreach (explode(' ', kb_normalize($s)) as $w) {
        if ($w === '' || in_array($w, $stop, true) || (mb_strlen($w) < 2 && !ctype_digit($w))) continue;
        $w = $syn[$w] ?? $w;
        if (preg_match('/^[a-z]{5,}s$/', $w)) $w = substr($w, 0, -1); // naive plural
        $out[] = $w;
    }
    return array_values(array_unique($out));
}

function kb_wants_human(string $text): bool
{
    return (bool)preg_match('/\b(human|agent|real person|someone|representative|operator|support team|talk to (you|someone|a person)|call me|whatsapp)\b|মানুষ|এজেন্ট|কর্মকর্তা|কথা বলতে|হোয়াটসঅ্যাপ/iu', $text);
}

/** @return array{entry: ?array, score: float, suggestions: array} */
function kb_match(string $text, string $lang = 'en'): array
{
    $qt = kb_tokens($text);
    if (!$qt) return ['entry' => null, 'score' => 0.0, 'suggestions' => []];
    $entries = db_all('SELECT * FROM kb_entries WHERE active = 1');
    $scored = [];
    foreach ($entries as $en) {
        $et = kb_tokens($en['question'] . ' ' . $en['keywords']);
        $qTokensOnly = kb_tokens($en['question']);
        $score = 0.0;
        foreach ($qt as $w) {
            $best = 0.0;
            foreach ($et as $x) {
                if ($w === $x) { $best = 1.0; break; }
                if (mb_strlen($w) >= 4 && mb_strlen($x) >= 4 && (str_starts_with($x, $w) || str_starts_with($w, $x))) $best = max($best, 0.7);
                elseif (mb_strlen($w) >= 5 && strlen($w) === mb_strlen($w) && levenshtein($w, $x) === 1) $best = max($best, 0.6);
            }
            if ($best > 0 && in_array($w, $qTokensOnly, true)) $best += 0.25;
            $score += $best;
        }
        $score = $score / max(2, count($qt)) + ($en['lang'] === $lang ? 0.08 : 0);
        if ($score > 0) $scored[] = [$score, $en];
    }
    usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
    $top = $scored[0] ?? null;
    $sugg = array_map(fn($s) => $s[1]['question'], array_slice(array_filter($scored, fn($s) => $s[0] >= 0.25), 1, 3));
    if ($top && $top[0] >= 0.45) return ['entry' => $top[1], 'score' => $top[0], 'suggestions' => $sugg];
    return ['entry' => null, 'score' => $top[0] ?? 0.0, 'suggestions' => array_slice(array_map(fn($s) => $s[1]['question'], array_filter($scored, fn($s) => $s[0] >= 0.2)), 0, 3)];
}

function whatsapp_link(?string $text = null): ?string
{
    $n = preg_replace('/\D/', '', (string)setting('whatsapp_number', ''));
    if (strlen($n) < 8) return null;
    return 'https://wa.me/' . $n . ($text ? '?text=' . rawurlencode($text) : '');
}

function chat_session(bool $create = false): ?array
{
    $tok = $_SESSION['chat_token'] ?? null;
    if ($tok) {
        $s = db_one('SELECT * FROM chat_sessions WHERE token = ?', [$tok]);
        if ($s) return $s;
    }
    if (!$create) return null;
    $tok = bin2hex(random_bytes(20));
    $u = current_user();
    db_insert('chat_sessions', [
        'token' => $tok, 'user_id' => $u['id'] ?? null, 'visitor_name' => $u['name'] ?? null, 'visitor_email' => $u['email'] ?? null,
        'page' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255), 'status' => 'bot', 'lang' => lang(),
        'ip' => client_ip(), 'country' => client_country(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $_SESSION['chat_token'] = $tok;
    return db_one('SELECT * FROM chat_sessions WHERE token = ?', [$tok]);
}

function chat_add(int $sessionId, string $sender, string $body, ?int $kbId = null, bool $unanswered = false): int
{
    $id = db_insert('chat_messages', ['session_id' => $sessionId, 'sender' => $sender, 'body' => mb_substr($body, 0, 2000), 'kb_id' => $kbId, 'unanswered' => $unanswered ? 1 : 0, 'created_at' => now()]);
    $upd = ['updated_at' => now()];
    db_update('chat_sessions', $upd, 'id = ?', [$sessionId]);
    if ($sender === 'visitor') db_exec('UPDATE chat_sessions SET unread_admin = unread_admin + 1 WHERE id = ?', [$sessionId]);
    return $id;
}
