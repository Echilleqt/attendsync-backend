<?php

return [
    'custom' => [
        'month' => [
            'date_format' => 'Select a valid month in YYYY-MM format.',
        ],
        'employee_id' => [
            'exists' => 'Select an employee that exists in the system.',
            'required' => 'Select an employee before generating the DTR.',
        ],
        'work_days.*' => [
            'in' => 'Select a valid day of the week.',
        ],
    ],
    'attributes' => [
        'am_time_in' => 'morning time in',
        'am_time_out' => 'morning time out',
        'pm_time_in' => 'afternoon time in',
        'pm_time_out' => 'afternoon time out',
        'late_threshold_am' => 'morning late threshold',
        'late_threshold_pm' => 'afternoon late threshold',
        'employee_id' => 'employee',
        'department_id' => 'department',
        'position_id' => 'position',
        'schedule_id' => 'schedule',
        'effective_date' => 'effective date',
        'from_date' => 'start date',
        'to_date' => 'end date',
        'device_uuid' => 'device ID',
        'public_key' => 'device public key',
        'face_verification_token' => 'face verification token',
        'biometric_verification_token' => 'biometric verification token',
    ],
];
