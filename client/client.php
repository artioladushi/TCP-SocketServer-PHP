Ky është një file tjetër për testim.
Vetëm admin mund të shkruajë në file të serverit.
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
