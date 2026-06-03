<?php

declare(strict_types=1);

use app\controller\Login;
use app\controller\Register;
use app\controller\Home;
use app\controller\Customer;
use app\middleware\Middleware;

// ── Rotas públicas ────────────────────────────────────────────────────────────

$app->get('/login',  Login::class . ':login')->add(Middleware::web());
$app->post('/login', Login::class . ':authenticate');

$app->post('/auth/google',          Login::class . ':googleOneTap');
$app->post('/auth/google/callback', Login::class . ':googleOneTap');

$app->get('/cadastro',  Register::class . ':register');
$app->post('/cadastro', Register::class . ':store');

$app->get('/logout', Login::class . ':logout');

// ── Rotas protegidas ──────────────────────────────────────────────────────────

$app->get('/',     Home::class . ':home')->add(Middleware::web());
$app->get('/home', Home::class . ':home')->add(Middleware::web());

$app->group('/cliente', function ($group) {
    $group->get('/lista',        Customer::class . ':list');
    $group->post('/listingdata', Customer::class . ':listingdata');
})->add(Middleware::web());