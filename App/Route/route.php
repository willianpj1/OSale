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
use App\Controller\Purchase;
use App\Controller\Report;
use App\Controller\Users;
use App\Controller\PaymentTerms;
use App\Controller\Installment;
use App\Controller\StockMovement;
use App\Middleware\Middleware;

// ── Rotas públicas ────────────────────────────────────────────────────────────

$app->get('/login',  Login::class . ':login');
$app->post('/login', Login::class . ':authenticate');

$app->post('/auth/google',              Login::class . ':googleOneTap');
$app->post('/authentication/google',    Login::class . ':googleOneTap');

$app->get('/cadastro',  Register::class . ':register');
$app->post('/cadastro', Register::class . ':store');

$app->get('/logout', Login::class . ':logout');

// ── Rotas protegidas ──────────────────────────────────────────────────────────

$app->get('/',     Home::class . ':home')->add(Middleware::web());;
$app->get('/home', Home::class . ':home')->add(Middleware::web());;
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
    $group->post('/{id}/contatoslista',   Customer::class . ':contactListingData');
    $group->post('/contato/{contactId}',  Customer::class . ':contactDelete');
    // Endereços
    $group->post('/{id}/endereco',        Customer::class . ':addressInsert');
    $group->post('/{id}/enderecoslista',   Customer::class . ':addressListingData');
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
    $group->post('/{id}/contatoslista',   Supplier::class . ':contactListingData');
    $group->post('/contato/{contactId}',  Supplier::class . ':contactDelete');
    // Endereços
    $group->post('/{id}/endereco',        Supplier::class . ':addressInsert');
    $group->post('/{id}/enderecoslista',   Supplier::class . ':addressListingData');
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

// ── Estoque ───────────────────────────────────────────────────────────────────

$app->group('/estoque', function ($group) {
    $group->get('/',              StockMovement::class . ':list');
    $group->post('/listingdata',  StockMovement::class . ':listingdata');
    $group->post('/ajustar',      StockMovement::class . ':ajustar');
    $group->get('/movimentacoes', StockMovement::class . ':history');
    $group->post('/movimentacoes/listingdata', StockMovement::class . ':historyData');
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
    $group->get('/lista',                     ServiceOrder::class . ':list')->add(Middleware::web());
    $group->get('/detalhes',                  ServiceOrder::class . ':details')->add(Middleware::web());
    $group->get('/detalhes/{id}',             ServiceOrder::class . ':details')->add(Middleware::web());
    $group->get('/buscar/produtos',           ServiceOrder::class . ':searchProducts')->add(Middleware::web());
    $group->get('/buscar/servicos',           ServiceOrder::class . ':searchServices')->add(Middleware::web());
    $group->get('/{id}/payment-terms',        ServiceOrder::class . ':paymentTerms')->add(Middleware::web());
    $group->get('/{id}/installment-preview',  ServiceOrder::class . ':installmentPreview')->add(Middleware::web());

    $group->post('/listingdata',              ServiceOrder::class . ':listingdata')->add(Middleware::api());
    $group->post('/inserir',                  ServiceOrder::class . ':insert')->add(Middleware::api());
    $group->post('/atualizar',                ServiceOrder::class . ':update')->add(Middleware::api());
    $group->post('/cancelar',                 ServiceOrder::class . ':cancel')->add(Middleware::api());
    $group->post('/concluir',                 ServiceOrder::class . ':finalize')->add(Middleware::api());
    $group->post('/excluir',                  ServiceOrder::class . ':delete')->add(Middleware::api());
    $group->post('/{id}/item',                ServiceOrder::class . ':itemInsert')->add(Middleware::api());
    $group->post('/{id}/item/{itemId}',       ServiceOrder::class . ':itemDelete')->add(Middleware::api());
});

// ── Compras ───────────────────────────────────────────────────────────────────

$app->group('/compras', function ($group) {
    $group->get('/lista',                 Purchase::class . ':list')->add(Middleware::web());
    $group->get('/detalhes',              Purchase::class . ':details')->add(Middleware::web());
    $group->get('/detalhes/{id}',         Purchase::class . ':details')->add(Middleware::web());
    $group->get('/buscar/produtos',       Purchase::class . ':searchProducts')->add(Middleware::web());

    $group->post('/listingdata',          Purchase::class . ':listingdata')->add(Middleware::api());
    $group->post('/inserir',              Purchase::class . ':insert')->add(Middleware::api());
    $group->post('/atualizar',            Purchase::class . ':update')->add(Middleware::api());
    $group->post('/receber',              Purchase::class . ':receive')->add(Middleware::api());
    $group->post('/cancelar',             Purchase::class . ':cancel')->add(Middleware::api());
    $group->post('/excluir',              Purchase::class . ':delete')->add(Middleware::api());
    $group->post('/{id}/item',            Purchase::class . ':itemInsert')->add(Middleware::api());
    $group->post('/{id}/item/{itemId}',   Purchase::class . ':itemDelete')->add(Middleware::api());
});


// ── Condições de Pagamento ────────────────────────────────────────────────────

$app->group('/payment', function ($group) {
    $group->get('/lista',                 PaymentTerms::class . ':list');
    $group->get('/detalhes',              PaymentTerms::class . ':details');
    $group->get('/detalhes/{id}',         PaymentTerms::class . ':details');
    $group->post('/listingdata',          PaymentTerms::class . ':listingdata');
    $group->post('/inserir',              PaymentTerms::class . ':insert');
    $group->post('/atualizar',            PaymentTerms::class . ':update');
    $group->post('/excluir',              PaymentTerms::class . ':delete');
});

// ── Parcelas (vinculadas a uma condição de pagamento) ────────────────────────

$app->group('/installment', function ($group) {
    $group->post('/inserir', Installment::class . ':insert');
    $group->post('/listar',  Installment::class . ':list');
    $group->post('/excluir', Installment::class . ':delete');
});
