<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Serve frontend home index at '/'
$routes->get('/', function() {
    return redirect()->to('/app/index.html');
});

// Public Auth Endpoints
$routes->post('api/register', 'AuthController::register', ['filter' => 'ratelimit']);
$routes->post('api/login', 'AuthController::login', ['filter' => 'ratelimit']);

// Shorten Endpoint (extracts JWT inside if provided, otherwise anonymous)
$routes->post('api/shorten', 'ShortenController::shorten', ['filter' => 'ratelimit']);

// Protected URL Management Endpoints (requires JWT + RateLimit)
$routes->group('api', ['filter' => ['jwt', 'ratelimit']], function($routes) {
    $routes->get('urls', 'UrlController::index');
    $routes->put('urls/(:num)', 'UrlController::update/$1');
    $routes->delete('urls/(:num)', 'UrlController::delete/$1');
    $routes->get('urls/(:num)/stats', 'AnalyticsController::stats/$1');
});

// Redirect endpoint (fallback, must be at the very bottom!)
$routes->get('(:segment)', 'RedirectController::redirect/$1', ['filter' => 'ratelimit']);
