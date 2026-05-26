<?php
// autorizar.php
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

$client = new Client();
$client->setAuthConfig('/home/znjdcxinp0ui/www/syspv/conciliacion/dev/v1/classes/client_secret_375206210163-2kmhfor5fs9q6lb8nhe4hp3ab0hm3v6t.apps.googleusercontent.com'); // Cambia por tu ruta real
$client->addScope(Gmail::GMAIL_SEND);
// Modifica esto según la URL que configuraste en Google Cloud Console
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']); 
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$tokenPath = 'token.json'; // Aquí se guardará el token de acceso

if (isset($_GET['code'])) {
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($accessToken['error'])) {
        file_put_contents($tokenPath, json_encode($accessToken));
        echo "¡Autenticación exitosa! El token se ha guardado. Ya puedes borrar este archivo.";
    } else {
        echo "Error al obtener el token: " . htmlspecialchars($accessToken['error_description']);
    }
    exit;
}

if (file_exists($tokenPath)) {
    echo "Ya estás autenticado. El archivo token.json ya existe.";
} else {
    $authUrl = $client->createAuthUrl();
    echo "<a href='" . filter_var($authUrl, FILTER_SANITIZE_URL) . "'>Haz clic aquí para vincular tu cuenta de Gmail</a>";
}