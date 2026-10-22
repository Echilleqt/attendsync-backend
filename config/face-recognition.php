<?php

return [
    'enabled' => env('FACE_RECOGNITION_ENABLED', true),
    'driver' => env('FACE_RECOGNITION_DRIVER', 'python-dlib'),
    'python_binary' => env('FACE_RECOGNITION_PYTHON_BINARY', 'python'),
    'model_version' => env('FACE_RECOGNITION_MODEL_VERSION', 'dlib_face_recognition_v1'),
    'threshold' => (float) env('FACE_RECOGNITION_THRESHOLD', 0.60),
    'attendance_threshold' => (float) env('FACE_RECOGNITION_ATTENDANCE_THRESHOLD', 0.45),
    'device_registration_threshold' => (float) env('FACE_RECOGNITION_DEVICE_REGISTRATION_THRESHOLD', 0.45),
    'verification_ttl_minutes' => (int) env('FACE_RECOGNITION_VERIFICATION_TTL_MINUTES', 2),
    'require_for_attendance' => (bool) env('FACE_RECOGNITION_REQUIRE_FOR_ATTENDANCE', false),
];
