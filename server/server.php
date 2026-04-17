<?php

set_time_limit(0);

$SERVER_IP = '0.0.0.0';   // server listens on all interfaces
$SERVER_PORT = 5000;      // you can change this
$FILES_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'server_files';

if (!is_dir($FILES_DIR)) {
    mkdir($FILES_DIR, 0777, true);
}

$users = [
    'admin' => ['password' => 'admin123', 'role' => 'full'],
    'user1' => ['password' => 'user123', 'role' => 'read'],
    'user2' => ['password' => 'user123', 'role' => 'read'],
    'user3' => ['password' => 'user123', 'role' => 'read'],
    'user4' => ['password' => 'user123', 'role' => 'read'],
];

$allowedExecCommands = [
    'time'  => function () {
        return date('Y-m-d H:i:s');
    },
    'pwd'   => function () {
        return getcwd();
    },
    'whoami' => function () {
        return get_current_user();
    },
    'ls'    => function () use ($FILES_DIR) {
        $files = scandir($FILES_DIR);
        $files = array_values(array_filter($files, fn($f) => $f !== '.' && $f !== '..'));
        return empty($files) ? 'No files found.' : implode(', ', $files);
    },
];
