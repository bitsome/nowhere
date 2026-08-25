<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 웹 푸시(VAPID) 설정
    |--------------------------------------------------------------------------
    | 서버에서 `VAPID::createVapidKeys()`로 생성한 키 쌍을 .env에 넣는다.
    */
    'vapid_subject' => env('VAPID_SUBJECT', 'mailto:no-reply@nowhere.app'),
    'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
];
