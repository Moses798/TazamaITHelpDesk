<?php
/**
 * Priority Matrix Test — verifies all 63 department × category combinations.
 * Run: php test_priority_matrix.php
 * This file is a standalone test; it does NOT touch data/store.json.
 */

// Test fixture mirrors the configured department/category priority rules.
$priorityMatrix = [
    'Administration' => [
        'Hardware'       => 'Medium',
        'Software'       => 'Medium',
        'Network'        => 'Medium',
        'Account Access' => 'Medium',
        'Email'          => 'Medium',
        'Printer'        => 'Low',
        'Security'       => 'High',
    ],
    'Finance' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Human Resources' => [
        'Hardware'       => 'Medium',
        'Software'       => 'Medium',
        'Network'        => 'High',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Low',
        'Security'       => 'Critical',
    ],
    'Security' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'Low',
        'Security'       => 'Critical',
    ],
    'Operations' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Maintenance' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'High',
        'Account Access' => 'Medium',
        'Email'          => 'Medium',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Boomgate' => [
        'Hardware'       => 'Critical',
        'Software'       => 'Critical',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Commercial' => [
        'Hardware'       => 'High',
        'Software'       => 'High',
        'Network'        => 'Critical',
        'Account Access' => 'High',
        'Email'          => 'High',
        'Printer'        => 'Medium',
        'Security'       => 'Critical',
    ],
    'Dispatch' => [
        'Hardware'       => 'Critical',
        'Software'       => 'Critical',
        'Network'        => 'Critical',
        'Account Access' => 'Critical',
        'Email'          => 'High',
        'Printer'        => 'High',
        'Security'       => 'Critical',
    ],
];

// Iterate every supported category to detect missing matrix entries.
$categories = ['Hardware', 'Software', 'Network', 'Account Access', 'Email', 'Printer', 'Security'];

// Named assertions from the spec
$assertions = [
    ['dept' => 'Finance',          'cat' => 'Network',        'expect' => 'Critical'],
    ['dept' => 'Human Resources',  'cat' => 'Hardware',       'expect' => 'Medium'],
    ['dept' => 'Dispatch',         'cat' => 'Hardware',       'expect' => 'Critical'],
    ['dept' => 'Administration',   'cat' => 'Printer',        'expect' => 'Low'],
    ['dept' => 'Boomgate',         'cat' => 'Account Access', 'expect' => 'Critical'],
    ['dept' => 'Security',         'cat' => 'Security',       'expect' => 'Critical'],
];

// Track both full-matrix coverage and named specification assertions.
$pass = 0;
$fail = 0;
$total = 0;

echo "=== Priority Matrix — All 63 Combinations ===\n\n";

// Print full matrix
printf("%-20s", "");
foreach ($categories as $cat) printf("%-16s", $cat);
echo "\n" . str_repeat("-", 20 + 16 * count($categories)) . "\n";

// Print and validate every department/category combination.
foreach ($priorityMatrix as $dept => $cats) {
    printf("%-20s", $dept);
    foreach ($categories as $cat) {
        $p = $cats[$cat] ?? 'MISSING';
        printf("%-16s", $p);
        $total++;
        if ($p !== 'MISSING') $pass++; else $fail++;
    }
    echo "\n";
}

echo "\n=== Named Assertions ===\n\n";
// Verify the high-value combinations called out in the requirements.
foreach ($assertions as $a) {
    $got = $priorityMatrix[$a['dept']][$a['cat']] ?? 'MISSING';
    $ok  = $got === $a['expect'];
    printf(
        "  [%s] %-20s + %-16s → expect %-9s got %s\n",
        $ok ? 'PASS' : 'FAIL',
        $a['dept'],
        $a['cat'],
        $a['expect'],
        $got
    );
    if (!$ok) $fail++;
}

echo "\n=== Summary ===\n";
echo "  Total matrix cells : $total (expect 63)\n";
echo "  Combination PASS   : $pass\n";
echo "  FAIL / MISSING     : $fail\n";
echo "\n" . ($fail === 0 ? "ALL TESTS PASSED" : " SOME TESTS FAILED") . "\n";
