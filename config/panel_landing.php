<?php

declare(strict_types=1);

return [
    'scp' => [
        'default' => 'queues',
        'tabs' => [
            'dashboard' => '/scp',
            'queues' => '/scp/queues',
        ],
    ],
    'admin' => [
        'default' => 'help-topics',
        'tabs' => [
            'help-topics' => '/admin/help-topics',
            'filters' => '/admin/filters',
            'slas' => '/admin/slas',
            'canned-responses' => '/admin/canned-responses',
            'email-config' => '/admin/email-config',
            'staff' => '/admin/staff',
            'teams' => '/admin/teams',
            'roles' => '/admin/roles',
            'departments' => '/admin/departments',
        ],
    ],
];
