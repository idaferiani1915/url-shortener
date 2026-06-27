<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Serve frontend home index at '/'
$routes->get('/', function() {
    return redirect()->to('/app/index.html');
});

// Serve the compiled frontend app and its assets directly from /app/*
$routes->get('app', function() {
    return redirect()->to('/app/index.html');
});

$routes->get('app/(:any)', function(string $path) {
    $filePath = FCPATH . 'app/' . $path;

    if (! is_file($filePath)) {
        return service('response')->setStatusCode(404);
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $contentType = match ($extension) {
        'html', 'htm' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        default => mime_content_type($filePath) ?: 'application/octet-stream',
    };

    return service('response')
        ->setHeader('Content-Type', $contentType)
        ->setBody(file_get_contents($filePath));
});

// Public Auth Endpoints
$routes->post('api/register', 'AuthController::register', ['filter' => 'ratelimit']);
$routes->post('api/login', 'AuthController::login', ['filter' => 'ratelimit']);
$routes->post('api/forgot-password', 'AuthController::forgotPassword', ['filter' => 'ratelimit']);
$routes->post('api/reset-password', 'AuthController::resetPassword', ['filter' => 'ratelimit']);

// Shorten Endpoint (extracts JWT inside if provided, otherwise anonymous)
$routes->post('api/shorten', 'ShortenController::shorten', ['filter' => 'ratelimit']);

// Protected URL Management Endpoints (requires JWT + RateLimit)
$routes->group('api', ['filter' => ['jwt', 'ratelimit']], function($routes) {
    $routes->get('urls', 'UrlController::index');
    $routes->put('urls/(:num)', 'UrlController::update/$1');
    $routes->delete('urls/(:num)', 'UrlController::delete/$1');
    $routes->get('urls/(:num)/stats', 'AnalyticsController::stats/$1');
});

// Serve SEO files explicitly just in case web server forwards all static files to CI
$routes->get('sitemap.xml', function() {
    $filePath = FCPATH . 'sitemap.xml';
    if (is_file($filePath)) {
        return service('response')
            ->setHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->setBody(file_get_contents($filePath));
    }
    return service('response')->setStatusCode(404);
});

$routes->get('robots.txt', function() {
    $filePath = FCPATH . 'robots.txt';
    if (is_file($filePath)) {
        return service('response')
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setBody(file_get_contents($filePath));
    }
    return service('response')->setStatusCode(404);
});

// Redirect endpoint (fallback, must be at the very bottom!)
$routes->get('(:segment)', 'RedirectController::redirect/$1', ['filter' => 'ratelimit']);
