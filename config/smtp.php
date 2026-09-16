<?php
date_default_timezone_set('America/Mexico_City');


function getSmtpConfig()
{

    error_log(__DIR__);
    return [
        // Método de envío de correo: 'resend', 'gmail_api' o 'smtp'
        'mailer_method' => 'resend',

        'resend' => [
            // API Key de Resend (https://resend.com/api-keys)
            // Ejemplo: 're_123456789_abcdefg...'
            'api_key' => 're_7L9Vn8cA_KWyyrXr8Bnrz7FEkMYBQw2Uk',
            'from_email' => 'noreply@carniceriasvictoria.com.mx',
            'from_name' => 'Sistema de Notificaciones Victoria',
        ],

        'smtp' => [
            // Para Gmail usa: smtp.gmail.com
            // Para GoDaddy (servicios cPanel/dedicados) usa: smtp.titan.email o el asignado en tu panel
            'host' => 'smtp.gmail.com',
            'auth' => true,
            'username' => 'ocv.facturacion1@gmail.com',
            'password' => 'xygv libz bxcc kxoo', // Si es Gmail, AQUÍ VA LA CONTRASEÑA DE APLICACIÓN
            'encryption' => 'tls',                // 'tls' o 'ssl'
            'port' => 587,                        // 587 para TLS, 465 para SSL
            'from_email' => 'ocv.facturacion1@gmail.com',
            'from_name' => 'Sistema de Notificaciones Victoria',
        ],

        'gmail_api' => [
            // Configuración para el SDK Oficial de Google (Gmail API)
            // Se puede configurar mediante credenciales directas de OAuth2:
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',

            // O alternativamente especificando las rutas a los archivos credentials.json y token.json
            'credentials_path' => __DIR__ . '/gmail/client.json',
            'token_path' => __DIR__ . '/gmail/token.json',

            'from_email' => 'ocv.facturacion1@gmail.com',
            'from_name' => 'Sistema de Notificaciones Victoria',
        ]
    ];
}


?>