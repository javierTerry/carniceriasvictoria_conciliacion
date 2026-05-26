<?php
date_default_timezone_set('America/Mexico_City');


function getSmtpConfig()
{
    return [
        // Método de envío de correo: 'smtp' o 'gmail_api'
        'mailer_method' => 'smtp',

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
            'credentials_path' => __DIR__ . '/gmail_credentials.json',
            'token_path' => __DIR__ . '/gmail_token.json',
            
            'from_email' => 'ocv.facturacion1@gmail.com',
            'from_name' => 'Sistema de Notificaciones Victoria',
        ]
    ];
}

/*
function getSmtpConfig()
{
    return [
        'smtp' => [
            // Para Gmail usa: smtp.gmail.com
            // Para GoDaddy (servicios cPanel/dedicados) usa: smtp.titan.email o el asignado en tu panel
            'host' => 'mail.carniceriasvictoria.com.mx', 
            'auth' => true,
            'username' => 'facturacion@carniceriasvictoria.com.mx',
            'password' => 'Temporal01$', // Si es Gmail, AQUÍ VA LA CONTRASEÑA DE APLICACIÓN
            'encryption' => 'ssl',                // 'tls' o 'ssl'
            'port' => 465,                        // 587 para TLS, 465 para SSL
            'from_email' => 'facturacion@carniceriasvictoria.com.mx',
            'from_name' => 'Sistema de Notificaciones Victoria',
        ]
];


}
*/


?>