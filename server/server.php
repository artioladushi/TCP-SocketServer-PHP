<?php

set_time_limit(0);

$SERVER_IP = '0.0.0.0';
$SERVER_PORT = 5000;
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
    'time' => function () {
        return date('Y-m-d H:i:s');
    },
    'pwd' => function () {
        return getcwd();
    },
    'whoami' => function () {
        return get_current_user();
    },
    'ls' => function () use ($FILES_DIR) {
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

function endResponse($socket): void
{
    fwrite($socket, "END" . PHP_EOL);
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

while (true) {
    $readSockets = [$server];
    foreach ($clients as $client) {
        $readSockets[] = $client;
    }

    $write = null;
    $except = null;

    if (stream_select($readSockets, $write, $except, null) === false) {
        break;
    }

    if (in_array($server, $readSockets, true)) {
        $newClient = @stream_socket_accept($server, 0);

        if ($newClient) {
            stream_set_blocking($newClient, false);

            $clientId = (int)$newClient;
            $clients[$clientId] = $newClient;
            $clientStates[$clientId] = [
                'authenticated' => false,
                'username' => null,
                'role' => null,
            ];

            echo "New client connected: " . getClientName($newClient) . PHP_EOL;

            sendLine($newClient, "Connected to Group13 TCP PHP Server");
            sendLine($newClient, "Please login using: LOGIN <username> <password>");
            sendLine($newClient, "Type HELP after login.");
            endResponse($newClient);
        }

        $serverIndex = array_search($server, $readSockets, true);
        if ($serverIndex !== false) {
            unset($readSockets[$serverIndex]);
        }
    }

    foreach ($readSockets as $clientSocket) {
        $clientId = (int)$clientSocket;
        $data = fgets($clientSocket);

        if ($data === false) {
            if (feof($clientSocket)) {
                echo "Client disconnected: " . getClientName($clientSocket) . PHP_EOL;
                fclose($clientSocket);
                unset($clients[$clientId], $clientStates[$clientId]);
            }
            continue;
        }

        $data = trim($data);
        if ($data === '') {
            continue;
        }

        $clientInfo = &$clientStates[$clientId];
        $remoteName = getClientName($clientSocket);

        echo "[{$remoteName}] {$data}" . PHP_EOL;

        if (stripos($data, 'LOGIN ') === 0) {
            $parts = preg_split('/\s+/', $data, 3);

            if (count($parts) < 3) {
                sendLine($clientSocket, "ERROR Invalid login format. Use: LOGIN <username> <password>");
                endResponse($clientSocket);
                continue;
            }

            $username = $parts[1];
            $password = $parts[2];

            if (!isset($users[$username]) || $users[$username]['password'] !== $password) {
                sendLine($clientSocket, "ERROR Invalid username or password.");
                endResponse($clientSocket);
                continue;
            }

            $clientInfo['authenticated'] = true;
            $clientInfo['username'] = $username;
            $clientInfo['role'] = $users[$username]['role'];

            sendLine($clientSocket, "OK Login successful.");
            sendLine($clientSocket, "Logged in as {$username} with role {$clientInfo['role']}.");
            sendLine($clientSocket, getHelpText());
            endResponse($clientSocket);
            continue;
        }

        if (!$clientInfo['authenticated']) {
            sendLine($clientSocket, "ERROR Please login first.");
            endResponse($clientSocket);
            continue;
        }

        if (strcasecmp($data, 'HELP') === 0) {
            sendLine($clientSocket, getHelpText());
            endResponse($clientSocket);
            continue;
        }

        if (strcasecmp($data, 'QUIT') === 0) {
            sendLine($clientSocket, "Bye.");
            endResponse($clientSocket);
            fclose($clientSocket);
            unset($clients[$clientId], $clientStates[$clientId]);
            echo "Client quit: {$remoteName}" . PHP_EOL;
            continue;
        }

        if (stripos($data, 'MSG ') === 0) {
            $message = substr($data, 4);
            sendLine($clientSocket, "SERVER RECEIVED MESSAGE: {$message}");
            endResponse($clientSocket);
            continue;
        }

        if (strcasecmp($data, 'LIST') === 0) {
            $files = scandir($FILES_DIR);
            $files = array_values(array_filter($files, fn($f) => $f !== '.' && $f !== '..'));

            if (empty($files)) {
                sendLine($clientSocket, "No files found.");
            } else {
                sendLine($clientSocket, "FILES: " . implode(', ', $files));
            }
            endResponse($clientSocket);
            continue;
        }

        if (stripos($data, 'READ ') === 0) {
            $filename = sanitizeFilename(substr($data, 5));
            $fullPath = $FILES_DIR . DIRECTORY_SEPARATOR . $filename;

            if (!file_exists($fullPath)) {
                sendLine($clientSocket, "ERROR File not found.");
                endResponse($clientSocket);
                continue;
            }

            $content = file_get_contents($fullPath);
            sendLine($clientSocket, "FILE_CONTENT_BEGIN");
            foreach (explode("\n", $content) as $line) {
                sendLine($clientSocket, rtrim($line, "\r"));
            }
            sendLine($clientSocket, "FILE_CONTENT_END");
            endResponse($clientSocket);
            continue;
        }

        if (stripos($data, 'WRITE ') === 0) {
            if ($clientInfo['role'] !== 'full') {
                sendLine($clientSocket, "ERROR Permission denied. WRITE allowed only for full-access client.");
                endResponse($clientSocket);
                continue;
            }

            $payload = substr($data, 6);
            $parts = explode('|', $payload, 2);

            if (count($parts) < 2) {
                sendLine($clientSocket, "ERROR Invalid WRITE format. Use: WRITE <filename>|<content>");
                endResponse($clientSocket);
                continue;
            }

            $filename = sanitizeFilename($parts[0]);
            $content = $parts[1];
            $fullPath = $FILES_DIR . DIRECTORY_SEPARATOR . $filename;

            file_put_contents($fullPath, $content . PHP_EOL, FILE_APPEND);
            sendLine($clientSocket, "OK Data written to {$filename}");
            endResponse($clientSocket);
            continue;
        }

        if (stripos($data, 'EXEC ') === 0) {
            if ($clientInfo['role'] !== 'full') {
                sendLine($clientSocket, "ERROR Permission denied. EXEC allowed only for full-access client.");
                endResponse($clientSocket);
                continue;
            }

            $command = trim(substr($data, 5));

            if (!isset($allowedExecCommands[$command])) {
                sendLine($clientSocket, "ERROR Command not allowed. Allowed: time, pwd, whoami, ls");
                endResponse($clientSocket);
                continue;
            }

            $result = $allowedExecCommands[$command]();
            sendLine($clientSocket, "EXEC_RESULT: {$result}");
            endResponse($clientSocket);
            continue;
        }

        sendLine($clientSocket, "ERROR Unknown command. Type HELP.");
        endResponse($clientSocket);
    }
}
