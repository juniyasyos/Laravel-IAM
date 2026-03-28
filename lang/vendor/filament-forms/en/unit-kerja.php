<?php

return [
    // Navigation & General Labels
    'navigation' => [
        'group' => 'IAM Management',
        'title' => 'Work Units',
        'plural' => 'Work Units',
        'description' => 'Manage work units in the system efficiently.',
    ],

    // Columns/Field Labels
    'fields' => [
        'id' => 'ID',
        'unit_name' => 'Work Unit Name',
        'description' => 'Description',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'users' => 'Users',
        'user_id' => 'User',
        'position' => 'Position',
    ],

    // Form Sections
    'form' => [
        'unit' => [
            'title' => 'Work Unit Information',
            'description' => 'Fill in the work unit details correctly.',
            'name_placeholder' => 'Enter work unit name',
            'description_placeholder' => 'Add a short description of this work unit',
            'helper_text' => 'The unit name must be unique and up to 100 characters.',
        ],
        'users' => [
            'title' => 'Users in Work Unit',
            'description' => 'Add users to this work unit.',
            'search_placeholder' => 'Search users...',
            'add_button' => 'Add User',
            'remove_button' => 'Remove User',
        ],
    ],

    'actions' => [
        'attach' => 'Attach User',
        'add' => 'Add Work Unit',
    ],
];
