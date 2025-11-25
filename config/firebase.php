<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Este archivo debe contener la ruta absoluta al archivo JSON de
    | credenciales del SDK de administrador de Firebase (admin SDK).
    | Puedes obtener este archivo desde la consola de Firebase:
    | Proyecto > Configuración > Cuentas de servicio > Generar nueva clave privada.
    |
    */

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opciones adicionales (si decides usarlas en el futuro)
    |--------------------------------------------------------------------------
    |
    | Aquí puedes definir opciones adicionales como base_url, database_uri, etc.
    | Por ahora solo usamos las credenciales.
    |
    */

    // 'database' => [
    //     'url' => env('FIREBASE_DATABASE_URL'),
    // ],

    // 'project_id' => env('FIREBASE_PROJECT_ID'),
];
