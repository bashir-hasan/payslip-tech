<?php
declare(strict_types=1);

/**
 * Countries, currencies and local payslip terminology.
 * Each company picks a country; that sets its currency, number format and default line labels.
 */

const CURRENCIES = [
    'USD' => ['$', 'US Dollars', 'Cents', 'intl'],
    'CAD' => ['CA$', 'Canadian Dollars', 'Cents', 'intl'],
    'EUR' => ['€', 'Euros', 'Cents', 'intl'],
    'GBP' => ['£', 'Pounds', 'Pence', 'intl'],
    'CHF' => ['CHF ', 'Swiss Francs', 'Rappen', 'intl'],
    'SEK' => ['kr ', 'Swedish Kronor', 'Öre', 'intl'],
    'NOK' => ['kr ', 'Norwegian Kroner', 'Øre', 'intl'],
    'DKK' => ['kr ', 'Danish Kroner', 'Øre', 'intl'],
    'PLN' => ['zł ', 'Złoty', 'Groszy', 'intl'],
    'AUD' => ['A$', 'Australian Dollars', 'Cents', 'intl'],
    'NZD' => ['NZ$', 'New Zealand Dollars', 'Cents', 'intl'],
    'SGD' => ['S$', 'Singapore Dollars', 'Cents', 'intl'],
    'AED' => ['AED ', 'Dirhams', 'Fils', 'intl'],
    'SAR' => ['SAR ', 'Riyals', 'Halalas', 'intl'],
    'MYR' => ['RM ', 'Ringgit', 'Sen', 'intl'],
    'BDT' => ['৳', 'Taka', 'Paisa', 'sa'],
    'INR' => ['₹', 'Rupees', 'Paise', 'sa'],
    'PKR' => ['Rs ', 'Rupees', 'Paisa', 'sa'],
    'LKR' => ['Rs ', 'Rupees', 'Cents', 'sa'],
    'NPR' => ['Rs ', 'Rupees', 'Paisa', 'sa'],
];

/** Payslip label presets. Keys match payroll line keys. */
function label_presets(): array
{
    $sa = [
        'title' => 'Payslip', 'tax_id' => 'TIN',
        'basic' => 'Basic Salary', 'house_rent' => 'House Rent', 'medical' => 'Medical Allowance', 'conveyance' => 'Conveyance', 'other_allowance' => 'Other Allowance', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
        'tax' => 'Income Tax (TDS)', 'pf' => 'Provident Fund', 'loan' => 'Loan / Advance', 'absence' => 'Absence Deduction', 'other' => 'Other Deduction',
    ];
    return [
        'south_asia' => $sa,
        'india' => array_merge($sa, ['tax_id' => 'PAN / GSTIN', 'house_rent' => 'House Rent Allowance (HRA)', 'other_allowance' => 'Special Allowance', 'pf' => 'Provident Fund (EPF)', 'other' => 'Professional Tax / ESI']),
        'pakistan' => array_merge($sa, ['tax_id' => 'NTN', 'tax' => 'Income Tax', 'pf' => 'Provident Fund / EOBI']),
        'us' => [
            'title' => 'Pay Stub', 'tax_id' => 'EIN',
            'basic' => 'Regular Pay', 'house_rent' => 'Housing Allowance', 'medical' => 'Health Stipend', 'conveyance' => 'Commuter Allowance', 'other_allowance' => 'Other Earnings', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
            'tax' => 'Income Tax (Federal & State)', 'pf' => '401(k) / Retirement', 'loan' => 'Loan / Advance', 'absence' => 'Unpaid Leave', 'other' => 'FICA (Social Security & Medicare)',
        ],
        'canada' => [
            'title' => 'Pay Stub', 'tax_id' => 'Business Number (BN)',
            'basic' => 'Regular Pay', 'house_rent' => 'Housing Allowance', 'medical' => 'Health Benefit', 'conveyance' => 'Travel Allowance', 'other_allowance' => 'Other Earnings', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
            'tax' => 'Income Tax (Federal & Provincial)', 'pf' => 'RRSP / Pension', 'loan' => 'Loan / Advance', 'absence' => 'Unpaid Leave', 'other' => 'CPP / QPP & EI',
        ],
        'uk' => [
            'title' => 'Payslip', 'tax_id' => 'Company No. / VAT',
            'basic' => 'Basic Pay', 'house_rent' => 'Housing Allowance', 'medical' => 'Health Allowance', 'conveyance' => 'Travel Allowance', 'other_allowance' => 'Other Allowances', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
            'tax' => 'PAYE Income Tax', 'pf' => 'Pension Contribution', 'loan' => 'Loan / Advance', 'absence' => 'Unpaid Leave', 'other' => 'National Insurance',
        ],
        'europe' => [
            'title' => 'Payslip', 'tax_id' => 'VAT / Company ID',
            'basic' => 'Base Salary', 'house_rent' => 'Housing Allowance', 'medical' => 'Health Allowance', 'conveyance' => 'Travel Allowance', 'other_allowance' => 'Other Allowances', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
            'tax' => 'Income Tax', 'pf' => 'Pension Contribution', 'loan' => 'Loan / Advance', 'absence' => 'Unpaid Leave', 'other' => 'Social Security',
        ],
        'global' => [
            'title' => 'Payslip', 'tax_id' => 'Tax ID',
            'basic' => 'Base Salary', 'house_rent' => 'Housing Allowance', 'medical' => 'Medical Allowance', 'conveyance' => 'Transport Allowance', 'other_allowance' => 'Other Allowances', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
            'tax' => 'Income Tax', 'pf' => 'Pension / Provident Fund', 'loan' => 'Loan / Advance', 'absence' => 'Unpaid Leave', 'other' => 'Other Deductions',
        ],
    ];
}

/** code => [name, currency, preset] */
function countries(): array
{
    $eu = fn($n, $c = 'EUR') => [$n, $c, 'europe'];
    return [
        'US' => ['United States', 'USD', 'us'],
        'CA' => ['Canada', 'CAD', 'canada'],
        'GB' => ['United Kingdom', 'GBP', 'uk'],
        'IE' => $eu('Ireland'), 'DE' => $eu('Germany'), 'FR' => $eu('France'), 'NL' => $eu('Netherlands'), 'BE' => $eu('Belgium'),
        'ES' => $eu('Spain'), 'IT' => $eu('Italy'), 'PT' => $eu('Portugal'), 'AT' => $eu('Austria'), 'FI' => $eu('Finland'),
        'GR' => $eu('Greece'), 'LU' => $eu('Luxembourg'), 'EE' => $eu('Estonia'), 'LV' => $eu('Latvia'), 'LT' => $eu('Lithuania'),
        'SK' => $eu('Slovakia'), 'SI' => $eu('Slovenia'), 'HR' => $eu('Croatia'), 'MT' => $eu('Malta'), 'CY' => $eu('Cyprus'),
        'SE' => $eu('Sweden', 'SEK'), 'NO' => $eu('Norway', 'NOK'), 'DK' => $eu('Denmark', 'DKK'), 'PL' => $eu('Poland', 'PLN'), 'CH' => $eu('Switzerland', 'CHF'),
        'BD' => ['Bangladesh', 'BDT', 'south_asia'],
        'IN' => ['India', 'INR', 'india'],
        'PK' => ['Pakistan', 'PKR', 'pakistan'],
        'LK' => ['Sri Lanka', 'LKR', 'south_asia'],
        'NP' => ['Nepal', 'NPR', 'south_asia'],
        'AE' => ['United Arab Emirates', 'AED', 'global'],
        'SA' => ['Saudi Arabia', 'SAR', 'global'],
        'SG' => ['Singapore', 'SGD', 'global'],
        'MY' => ['Malaysia', 'MYR', 'global'],
        'AU' => ['Australia', 'AUD', 'global'],
        'NZ' => ['New Zealand', 'NZD', 'global'],
        'XX' => ['Other country', 'USD', 'global'],
    ];
}

function country_name(?string $code): string
{
    return countries()[$code ?? 'XX'][0] ?? 'Other';
}

function default_country(): string
{
    $c = client_country();
    return $c && isset(countries()[$c]) ? $c : 'US';
}

/** Resolved line labels for a company (preset + its own overrides). */
function company_labels(array $c): array
{
    $presetKey = countries()[$c['country'] ?? 'BD'][2] ?? 'global';
    $labels = label_presets()[$presetKey] ?? label_presets()['global'];
    $custom = json_decode((string)($c['labels'] ?? ''), true) ?: [];
    foreach ($custom as $k => $v) if (isset($labels[$k]) && trim((string)$v) !== '') $labels[$k] = mb_substr(trim((string)$v), 0, 60);
    return $labels;
}

/** Label for a line on a payslip; translated to Bangla only when it is a built-in English label. */
function line_label(array $labels, string $key, string $lang): string
{
    $l = $labels[$key] ?? $key;
    return $lang === 'bn' ? t($l, 'bn') : $l;
}

/* ---------------- Money ---------------- */

function currency_context(?string $set = null): string
{
    static $cur = 'BDT';
    if ($set !== null && isset(CURRENCIES[$set])) $cur = $set;
    return $cur;
}

function currency_symbol(?string $cur = null): string
{
    return CURRENCIES[$cur ?? currency_context()][0] ?? '';
}

function payment_methods_for(?string $country): array
{
    $all = payment_methods();
    if ($country === 'BD') return $all;
    unset($all['bkash'], $all['nagad'], $all['rocket']);
    return $all;
}
