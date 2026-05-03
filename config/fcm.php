<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FCM V1 API Configuration
    |--------------------------------------------------------------------------
    | Uses the Firebase HTTP V1 API with OAuth2 service account authentication.
    | The legacy server_key is no longer used.
    */

    'project_id' => env('FCM_PROJECT_ID'),
    'credentials_path' => env('FCM_CREDENTIALS_PATH'),
];
