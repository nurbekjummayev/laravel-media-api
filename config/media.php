<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    | Paket route'lari shu prefix va middleware ostida yuklanadi.
    | `view`/`download` token orqali ishlaydi va auth'siz ochiladi.
    | `upload_middleware`/`delete_middleware` — store/destroy uchun ruxsat (permission).
    */
    'prefix' => 'api/v1',
    'middleware' => ['auth:api'],
    'upload_middleware' => ['can:media.upload'],
    'delete_middleware' => ['can:media.delete'],

    /*
    |--------------------------------------------------------------------------
    | Storage disks
    |--------------------------------------------------------------------------
    | Provider bu disklarni `base_path('media/private|public')` ga ro'yxatdan
    | o'tkazadi. `type=private` → `disk`, `type=public` → `public_disk`.
    */
    'disk' => 'media',
    'public_disk' => 'media_public',
    'private_root' => base_path('media/private'),
    'public_root' => base_path('media/public'),

    /*
    |--------------------------------------------------------------------------
    | Owner model
    |--------------------------------------------------------------------------
    | Media egasi (`owner_id`) shu modelga bog'lanadi — User yoki boshqa istalgan
    | model bo'lishi mumkin. null bo'lsa config('auth.providers.users.model').
    */
    'owner_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'max_size' => env('MEDIA_MAX_SIZE', 102400), // KB (~100MB)
    'max_files_per_request' => 20,

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', 'csv', 'txt',
    ],

    'blocked_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps', 'pht', 'phar',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr', 'jar',
        'sh', 'bash', 'zsh', 'ksh', 'cgi', 'pl', 'py', 'rb',
        'js', 'mjs', 'cjs', 'html', 'htm', 'xhtml', 'shtml', 'htaccess', 'htpasswd', 'env',
    ],

    /*
    | Public diskda QO'SHIMCHA bloklanadigan kengaytmalar. Public fayllar web-server
    | tomonidan to'g'ridan-to'g'ri (controller'siz) beriladi, shuning uchun ularga
    | CSP/nosniff qo'sha olmaymiz. Brauzerda skript ishga tushira oladigan aktiv
    | kontent (SVG, XML...) bu yerda bloklanadi — private diskda esa ruxsat.
    */
    'public_blocked_extensions' => [
        'svg', 'svgz', 'xml', 'xsl', 'xslt', 'swf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL (private view/download)
    |--------------------------------------------------------------------------
    | `view`/`download` Laravel'ning vaqtinchalik imzolangan (signed) URL'lari
    | orqali ochiladi. Imzo APP_KEY bilan tekshiriladi, query'da o'zgartirib
    | bo'lmaydi va shu daqiqadan keyin muddati o'tadi.
    */
    'url_ttl' => 60, // daqiqa

    /*
    |--------------------------------------------------------------------------
    | Orphan cleanup
    |--------------------------------------------------------------------------
    | `attached=false` va shu soatdan eski media'lar `media:purge` bilan o'chadi.
    */
    'purge_after_hours' => 24,
];
