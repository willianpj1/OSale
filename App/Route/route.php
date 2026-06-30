<?php

declare(strict_types=1);

use App\Controller\Login;
use App\Controller\Register;
use App\Controller\Home;
use App\Controller\Customer;
use App\Controller\Product;
use App\Controller\Supplier;
use App\Controller\Service;
use App\Controller\ServiceOrder;
use App\Controller\Sale;
use App\Controller\Report;
use App\Controller\Users;
use App\Middleware\Middleware;

// ── Rotas públicas ────────────────────────────────────────────────────────────

$app->get('/login',  Login::class . ':login');
$app->post('/login', Login::class . ':authenticate');

$app->post('/auth/google',          Login::class . ':googleOneTap');
$app->post('/auth/google/callback', Login::class . ':googleOneTap');

$app->get('/cadastro',  Register::class . ':register');
$app->post('/cadastro', Register::class . ':store');

$app->get('/logout', Login::class . ':logout');

// ── Rotas protegidas ──────────────────────────────────────────────────────────

$app->get('/',     Home::class . ':home');
$app->get('/home', Home::class . ':home');
$app->get('/relatorio', Report::class . ':report');
$app->get('/relatorio/curva-abc', Report::class . ':curvaAbc');
$app->get('/relatorio/resumo',    Report::class . ':resumo');

// ── Usuários ──────────────────────────────────────────────────────────────────

$app->group('/usuarios', function ($group) {
    $group->get('/lista',                 Users::class . ':list');
    $group->get('/detalhes',              Users::class . ':details');
    $group->get('/detalhes/{id}',         Users::class . ':details');
    $group->post('/listingdata',          Users::class . ':listingdata');
    $group->post('/inserir',              Users::class . ':insert');
    $group->post('/atualizar',            Users::class . ':update');
    $group->post('/excluir',              Users::class . ':delete');
    // Contatos
    $group->post('/{id}/contato',         Users::class . ':contactInsert');
    $group->post('/contato/{contactId}',  Users::class . ':contactDelete');
    // Endereços
    $group->post('/{id}/endereco',        Users::class . ':addressInsert');
    $group->post('/endereco/{addressId}', Users::class . ':addressDelete');
});

// ── Clientes ──────────────────────────────────────────────────────────────────

$app->group('/cliente', function ($group) {
    $group->get('/lista',                 Customer::class . ':list');
    $group->get('/detalhes',              Customer::class . ':details');
    $group->get('/detalhes/{id}',         Customer::class . ':details');
    $group->post('/listingdata',          Customer::class . ':listingdata');
    $group->post('/inserir',              Customer::class . ':insert');
    $group->post('/atualizar',            Customer::class . ':update');
    $group->post('/excluir',              Customer::class . ':delete');
    // Contatos
    $group->post('/{id}/contato',         Customer::class . ':contactInsert');
    $group->post('/contato/{contactId}',  Customer::class . ':contactDelete');
    // Endereços
    $group->post('/{id}/endereco',        Customer::class . ':addressInsert');
    $group->post('/endereco/{addressId}', Customer::class . ':addressDelete');
});

// ── Fornecedores ──────────────────────────────────────────────────────────────

$app->group('/fornecedor', function ($group) {
    $group->get('/lista',                 Supplier::class . ':list');
    $group->get('/detalhes',              Supplier::class . ':details');
    $group->get('/detalhes/{id}',         Supplier::class . ':details');
    $group->post('/listingdata',          Supplier::class . ':listingdata');
    $group->post('/inserir',              Supplier::class . ':insert');
    $group->post('/atualizar',            Supplier::class . ':update');
    $group->post('/excluir',              Supplier::class . ':delete');
    // Contatos
    $group->post('/{id}/contato',         Supplier::class . ':contactInsert');
    $group->post('/contato/{contactId}',  Supplier::class . ':contactDelete');
    // Endereços
    $group->post('/{id}/endereco',        Supplier::class . ':addressInsert');
    $group->post('/endereco/{addressId}', Supplier::class . ':addressDelete');
});

// ── Produtos ──────────────────────────────────────────────────────────────────

$app->group('/produto', function ($group) {
    $group->get('/lista',                 Product::class . ':list');
    $group->get('/detalhes',              Product::class . ':details');
    $group->get('/detalhes/{id}',         Product::class . ':details');
    $group->post('/listingdata',          Product::class . ':listingdata');
    $group->post('/inserir',              Product::class . ':insert');
    $group->post('/atualizar',            Product::class . ':update');
    $group->post('/excluir',              Product::class . ':delete');
});

// ── Serviços ──────────────────────────────────────────────────────────────────

$app->group('/servico', function ($group) {
    $group->get('/lista',                 Service::class . ':list');
    $group->get('/detalhes',              Service::class . ':details');
    $group->get('/detalhes/{id}',         Service::class . ':details');
    $group->post('/listingdata',          Service::class . ':listingdata');
    $group->post('/inserir',              Service::class . ':insert');
    $group->post('/atualizar',            Service::class . ':update');
    $group->post('/excluir',              Service::class . ':delete');
});

// ── Ordens de Serviço ─────────────────────────────────────────────────────────

$app->group('/os', function ($group) {
    $group->get('/lista',                 ServiceOrder::class . ':list')->add(Middleware::web());
    $group->get('/detalhes',              ServiceOrder::class . ':details')->add(Middleware::web());
    $group->get('/detalhes/{id}',         ServiceOrder::class . ':details')->add(Middleware::web());
    $group->get('/buscar/produtos',       ServiceOrder::class . ':searchProducts')->add(Middleware::web());
    $group->get('/buscar/servicos',       ServiceOrder::class . ':searchServices')->add(Middleware::web());

    $group->post('/listingdata',          ServiceOrder::class . ':listingdata')->add(Middleware::api());
    $group->post('/inserir',              ServiceOrder::class . ':insert')->add(Middleware::api());
    $group->post('/atualizar',            ServiceOrder::class . ':update')->add(Middleware::api());
    $group->post('/concluir',             ServiceOrder::class . ':finalize')->add(Middleware::api());
    $group->post('/excluir',              ServiceOrder::class . ':delete')->add(Middleware::api());
    $group->post('/{id}/item',            ServiceOrder::class . ':itemInsert')->add(Middleware::api());
    $group->post('/{id}/item/{itemId}',   ServiceOrder::class . ':itemDelete')->add(Middleware::api());
});

// ── Vendas ────────────────────────────────────────────────────────────────────

$app->group('/venda', function ($group) {
    $group->get('/lista',                 Sale::class . ':list');
    $group->get('/detalhes',              Sale::class . ':details');
    $group->get('/detalhes/{id}',         Sale::class . ':details');
    $group->post('/listingdata',          Sale::class . ':listingdata');
    $group->post('/inserir',              Sale::class . ':insert');
    $group->post('/atualizar',            Sale::class . ':update');
    $group->post('/finalizar',            Sale::class . ':finalize');
    $group->post('/excluir',              Sale::class . ':delete');
    // Itens
    $group->post('/{id}/item',            Sale::class . ':itemInsert');
    $group->post('/{id}/item/{itemId}',   Sale::class . ':itemDelete');
    // Busca
    $group->get('/buscar/produtos',       Sale::class . ':searchProducts');
    $group->get('/buscar/servicos',       Sale::class . ':searchServices');
});
