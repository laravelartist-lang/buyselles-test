<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase project (buyselles-production)
    |--------------------------------------------------------------------------
    |
    | Web client SDK config. Values match Firebase Console → Project Settings
    | → BuySelles Web app (1:981094374492:web:62bd0fb64f44dcebce5a99).
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'buyselles-production'),

    'web' => [
        'apiKey' => env('FIREBASE_WEB_API_KEY', 'AIzaSyCiir3f-99LfW81gecqocO1I7I1xwMOo4U'),
        'authDomain' => env('FIREBASE_WEB_AUTH_DOMAIN', 'buyselles-production.firebaseapp.com'),
        'projectId' => env('FIREBASE_PROJECT_ID', 'buyselles-production'),
        'storageBucket' => env('FIREBASE_WEB_STORAGE_BUCKET', 'buyselles-production.firebasestorage.app'),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID', '981094374492'),
        'appId' => env('FIREBASE_WEB_APP_ID', '1:981094374492:web:62bd0fb64f44dcebce5a99'),
        'measurementId' => env('FIREBASE_WEB_MEASUREMENT_ID', 'G-WVNMH70N6K'),
    ],

    /*
    | OAuth Web client ID (client_type 3) — required as serverClientId for Google Sign-In on Android.
    */
    'google_web_client_id' => env(
        'FIREBASE_GOOGLE_WEB_CLIENT_ID',
        '981094374492-5p2kd4chii7pfn4u5sjl961ib2lmuuil.apps.googleusercontent.com'
    ),

];
