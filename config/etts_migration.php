<?php

declare(strict_types=1);

return [
    // Maps ETTS endorsing_offices/receiving_offices name => OPTS abbreviation
    'office_mapping' => [
        'MMO - Personal Staff' => 'MMO-PS',
        'MMO - Management Information System Division' => 'MMO-MIS',
        'MMO - Municipal Disaster Risk Reduction and Management Office' => 'MDRRMO',
        'Municipal Disaster Risk Reduction and Management Office' => 'MDRRMO',
        'MMO - Public Affairs, Information and Assistance Division' => 'MMO-PAIAD',
        'MMO - Bids and Award Committee' => 'MMO-BAC',
        'MMO - Procurement Office' => 'MMO-PO',
        'MMO - Livelihood Division' => 'MMO-LD',
        'MMO - Permits and Licenses Division' => 'MMO-BPLO',
        'MMO - General Services Office' => 'MMO-PSMD',
        'MMO - Property Supply & Management Office' => 'MMO-PSMD',
        'MMO - Nutrition Division' => 'MMO-ND',
        'MMO - Population Development Division' => 'MMO-PDD',
        'MMO - Economic Enterprise Division' => 'MEMO',
        'Municipal Enterprise Management Office' => 'MEMO',
        'MMO - Barangay Affairs Division' => 'MMO-BAD',
        'MMO - Human Resource Management Office' => 'MMO-HRMO',
        'MMO - Civil Security Unit' => 'MMO-CSU',
        'Office of the Sangguniang Bayan' => 'SBO',
        'Municipal Planning and Development Office' => 'MPDO',
        'Municipal Budget Office' => 'MBO',
        'Municipal Accounting Office' => 'MACCO',
        'Municipal Treasurer Office' => 'MTO',
        'Municipal Engineer Office' => 'MEO',
        'Municipal Assessor Office' => 'MASSO',
        'Municipal Health Office' => 'MHO',
        "Municipal Mayor's Office" => 'MMO',
        'Municipal Agriculture Office' => 'MAO',
        'Municipal Civil Registrar Office' => 'MCRO',
        'Municipal Social Welfare and Development Office' => 'MSWDO',
        'Municipal Environment and Natural Resources Office' => 'MENRO',
        'Commission On Audit' => 'COA',
        'Commission On Elections' => 'COMELEC',
        'Philippine National Police' => 'PNP',
        'Bureau of Fire Protection' => 'BFP',
        'Department of Interior Local Government' => 'DILG',
        'Local School Board' => 'LSB',
        'Municipal Trial court' => 'MTC',
        // ETTS 2025: ID 35 changed from Municipal Trial Court to Supplier (no OPTS equivalent)
        'Supplier' => null,
    ],

    // ETTS 2025 statuses: 1=None, 2=In Progress, 3=Cancelled, 4=Complete
    'status_mapping' => [
        1 => 'Created',
        2 => 'In Progress',
        3 => 'Cancelled',
        4 => 'Completed',
    ],

    'role_mapping' => [
        'Guest' => 'Viewer',
        'Standard' => 'Endorser',
        'Administrator' => 'Administrator',
    ],

    // ETTS 2025 process_types_id => category
    'process_type_mapping' => [
        1 => 'PR',   // Purchase Request
        2 => 'PO',   // Purchase Order
        3 => 'VCH',  // Voucher
    ],

    'fund_type_prefixes' => ['GF', 'TF', 'SEF'],

    'temp_db_prefix' => 'etts_import_',
];
