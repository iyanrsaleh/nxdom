<?php
/**
 * API Routes
 * Define all API routes for the application here
 * PENTING: Route spesifik HARUS di atas route generic!
 */

// Router instance is available from parent scope
// $router variable is passed from index.php

// ============================================
// Google Authentication API Routes
// ============================================
$router->add('/api/google-signup', 'Api/GoogleAuthController@signup', ['POST']);
$router->add('/api/google-signin', 'Api/GoogleAuthController@signin', ['POST']);  
$router->add('/api/google-test', 'Api/GoogleAuthController@test', ['GET']);
$router->add('/api/register', 'Api/GoogleAuthController@register', ['POST']);
$router->add('/api/forgot', 'Api/GoogleAuthController@forgot', ['POST']);

// ============================================
// Generic API Routes (HARUS DI BAWAH!)
// ============================================
$router->any('/api', 'ApiController@index');
$router->any('/api/', 'ApiController@index');
$router->any('/api/{params}', 'ApiController@index');



// Register API middleware using method names instead of closures
// $router->middleware('auth_api', 'ApiMiddleware@authenticate');
// $router->middleware('admin_api', 'ApiMiddleware@authorize');

// Note: Middleware with closures disabled for route caching compatibility
// Alternative: Create dedicated middleware classes
