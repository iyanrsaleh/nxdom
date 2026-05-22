<?php
/**
 * Web Routes untuk NexaUi Framework v2.0
 * Enhanced with NexaMapping Support - Simplified Version
 */

// Router instance is passed by RouteLoader or Application
// Don't try to get it here, it will be available in scope

// Root route - direct to HomeController (uses default 'theme' device type)
$router->add('/', 'FrontendController@index');

// Home routes
$router->add('/home', 'FrontendController@index');

// Auth routes
$router->add('/logout', 'OauthController@logout');
$router->add('/signin', 'OauthController@signin');
$router->add('/signup', 'OauthController@signup');

// Product routes - handled by frontend routing automatically
// /product, /product/makana, /product/makana/nasi-goreng akan otomatis di-handle
// Tidak perlu register manual karena tryFrontendRouting() akan menanganinya




$router->add('/office',  'ExcelExampleController@index');

// Drive, avatar, images - direktori statik di NexaRouter::registerStaticDirectoryRoutes()

$router->get('{Y}/{params}', 'Frontend/BlogController@detail');
// Define routes views/theme

// Dokumentasi NexaUI (10 topik)
// $router->add('/docs', 'DocsController@index');
// $router->add('/docs/{params}', 'DocsController@topic');

// Rute untuk NexaJs - menangani /app/main.js dan rute app lainnya
$router->add('/debug', 'DebugController@index');
$router->add('/debug/{params}', 'DebugController@index');

$router->add('/eventload', 'FileController@eventload');
$router->add('/eventMarkdownload', 'FileController@eventMarkdownload');
$router->add('/file/{params}', 'FileController@index');
$router->add('/app/{params}', 'JsController@index');
// $router->add('/docs',  'Docs/indexController@index');
// $router->add('/docs/{params}',  'Docs/indexController@index');
