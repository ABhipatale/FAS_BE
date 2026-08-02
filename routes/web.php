<?php

use Illuminate\Support\Facades\Route;

// For API-only applications, you can keep this minimal or remove it entirely
// All your application routes should be in api.php

// Serve the built public/index.html at the root. This has to go through a route
// rather than the web server: `artisan serve` uses Laravel's server.php, which
// excludes "/" from static file serving and always hands it to index.php.
Route::get('/', function () {
    $page = public_path('index.html');

    abort_unless(file_exists($page), 404);

    return response()->file($page);
});