<?php
/** Shared department/category priority rules. */

function td_priority_matrix() {
    return [
        'Administration' => ['Hardware' => 'Medium', 'Software' => 'Medium', 'Network' => 'Medium', 'Account Access' => 'Medium', 'Email' => 'Medium', 'Printer' => 'Low', 'Security' => 'High'],
        'Finance' => ['Hardware' => 'High', 'Software' => 'High', 'Network' => 'Critical', 'Account Access' => 'High', 'Email' => 'High', 'Printer' => 'Medium', 'Security' => 'Critical'],
        'Human Resources' => ['Hardware' => 'Medium', 'Software' => 'Medium', 'Network' => 'High', 'Account Access' => 'High', 'Email' => 'High', 'Printer' => 'Low', 'Security' => 'Critical'],
        'Security' => ['Hardware' => 'High', 'Software' => 'High', 'Network' => 'Critical', 'Account Access' => 'Critical', 'Email' => 'High', 'Printer' => 'Low', 'Security' => 'Critical'],
        'Operations' => ['Hardware' => 'High', 'Software' => 'High', 'Network' => 'Critical', 'Account Access' => 'High', 'Email' => 'High', 'Printer' => 'Medium', 'Security' => 'Critical'],
        'Maintenance' => ['Hardware' => 'High', 'Software' => 'High', 'Network' => 'High', 'Account Access' => 'Medium', 'Email' => 'Medium', 'Printer' => 'Medium', 'Security' => 'Critical'],
        'Boomgate' => ['Hardware' => 'Critical', 'Software' => 'Critical', 'Network' => 'Critical', 'Account Access' => 'Critical', 'Email' => 'High', 'Printer' => 'Medium', 'Security' => 'Critical'],
        'Commercial' => ['Hardware' => 'High', 'Software' => 'High', 'Network' => 'Critical', 'Account Access' => 'High', 'Email' => 'High', 'Printer' => 'Medium', 'Security' => 'Critical'],
        'Dispatch' => ['Hardware' => 'Critical', 'Software' => 'Critical', 'Network' => 'Critical', 'Account Access' => 'Critical', 'Email' => 'High', 'Printer' => 'High', 'Security' => 'Critical'],
    ];
}

function td_priority_for($dept, $category) {
    $matrix = td_priority_matrix();
    return $matrix[$dept][$category] ?? 'Medium';
}
