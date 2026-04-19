<?php

$SERVER_IP = '192.168.100.113';   // ndryshoje me IP reale të serverit në rrjet
$SERVER_PORT = 5000;

$socket = stream_socket_client("tcp://{$SERVER_IP}:{$SERVER_PORT}", $errno, $errstr, 30);

if (!$socket) {
    die("Connection failed: $errstr ($errno)\n");
}

echo "U lidh me serverin ({$SERVER_IP}:{$SERVER_PORT})\n";

function sendCommand($socket, string $cmd): void
{
    fwrite($socket, $cmd . PHP_EOL);
}

function readResponse($socket): void
{
    while (($line = fgets($socket)) !== false) {
        $line = trim($line);

        if ($line === 'END') {
            break;
        }

        echo "SERVER -> {$line}\n";
    }
}

function showMenu(): void
{
    echo "\n--- MENU ---\n";
    echo "1. Login\n";
    echo "2. List files\n";
    echo "3. Read file\n";
    echo "4. Write file (admin)\n";
    echo "5. Execute command (admin)\n";
    echo "6. Send message\n";
    echo "7. Help\n";
    echo "8. Exit\n";
    echo "Zgjedhja: ";
}

readResponse($socket);

while (true) {
    showMenu();
    $choice = trim(fgets(STDIN));

    switch ($choice) {
        case '1':
            echo "Username: ";
            $user = trim(fgets(STDIN));

            echo "Password: ";
            $pass = trim(fgets(STDIN));

            sendCommand($socket, "LOGIN {$user} {$pass}");
            readResponse($socket);
            break;

        case '2':
            sendCommand($socket, "LIST");
            readResponse($socket);
            break;

        case '3':
            echo "File name: ";
            $file = trim(fgets(STDIN));

            sendCommand($socket, "READ {$file}");
            readResponse($socket);
            break;

        case '4':
            echo "File name: ";
            $file = trim(fgets(STDIN));

            echo "Content: ";
            $content = trim(fgets(STDIN));

            sendCommand($socket, "WRITE {$file}|{$content}");
            readResponse($socket);
            break;

        case '5':
            echo "Command (time/pwd/whoami/ls): ";
            $cmd = trim(fgets(STDIN));

            sendCommand($socket, "EXEC {$cmd}");
            readResponse($socket);
            break;

        case '6':
            echo "Message: ";
            $msg = trim(fgets(STDIN));

            sendCommand($socket, "MSG {$msg}");
            readResponse($socket);
            break;

        case '7':
            sendCommand($socket, "HELP");
            readResponse($socket);
            break;

        case '8':
            sendCommand($socket, "QUIT");
            readResponse($socket);
            fclose($socket);
            echo "Klienti u mbyll.\n";
            exit;

        default:
            echo "Opsion i pavlefshëm.\n";
    }
}
