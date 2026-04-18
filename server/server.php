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
$server = stream_socket_server("tcp://{$SERVER_IP}:{$SERVER_PORT}", $errno, $errstr);

if (!$server) {
    die("Failed to start server: $errstr ($errno)\n");
}

stream_set_blocking($server, false);

echo "TCP Server started on {$SERVER_IP}:{$SERVER_PORT}\n";
echo "Files directory: {$FILES_DIR}\n";

$clients = [];
$clientStates = [];

function sendLine($socket, string $message): void
{
    fwrite($socket, $message . PHP_EOL);
}

function getClientName($socket): string
{
    $name = stream_socket_get_name($socket, true);
    return $name ?: 'unknown-client';
}

function sanitizeFilename(string $filename): string
{
    $filename = trim($filename);
    $filename = basename($filename);
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
}

function getHelpText(): string
{
    return implode(PHP_EOL, [
        "Available commands:",
        "LOGIN <username> <password>",
        "MSG <text>",
        "LIST",
        "READ <filename>",
        "WRITE <filename>|<content>      (full access only)",
        "EXEC <time|pwd|whoami|ls>       (full access only)",
        "HELP",
        "QUIT"
    ]);
}
