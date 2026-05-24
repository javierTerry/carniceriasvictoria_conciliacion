<?php
date_default_timezone_set('America/Mexico_City');


function getSmtpConfig()
{
    return [
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