<?php

$SERVER_IP = '10.180.52.41';   // change this to real server IP for other devices
$SERVER_PORT = 5000;

$client = stream_socket_client("tcp://{$SERVER_IP}:{$SERVER_PORT}", $errno, $errstr, 30);

if (!$client) {
    die("Connection failed: $errstr ($errno)\n");
}

echo "Connected to server {$SERVER_IP}:{$SERVER_PORT}\n";
echo "Write commands and press Enter.\n";
echo "Example: LOGIN admin admin123\n";
echo "Type EXIT to close client.\n\n";

stream_set_blocking($client, false);
stream_set_blocking(STDIN, false);
stream_set_blocking(STDIN, false);

while (true) {
    $read = [$client, STDIN];
    $write = null;
    $except = null;

    if (stream_select($read, $write, $except, null) === false) {
        break;
    }

    foreach ($read as $r) {
        if ($r === $client) {
            $response = fgets($client);

            if ($response === false) {
                if (feof($client)) {
                    echo "Server closed connection.\n";
                    fclose($client);
                    exit;
                }
                continue;
            }

            echo "[SERVER] " . $response;
        }

        if ($r === STDIN) {
            $input = fgets(STDIN);
            if ($input === false) {
                continue;
            }

            $input = trim($input);

            if ($input === '') {
                continue;
            }

            if (strtoupper($input) === 'EXIT') {
                fwrite($client, "QUIT\n");
                fclose($client);
                exit;
            }

            fwrite($client, $input . "\n");
        }
    }
}

