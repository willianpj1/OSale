<?php

declare(strict_types=1);

if (!defined('DIR_VIEWS')) {
    define('DIR_VIEWS', dirname(__DIR__, 2) . '/App/View/');
}

if (!defined('EXT_VIEWS')) {
    define('EXT_VIEWS', '.html');
}

use App\Controller\Service;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('list retorna status 200', function () {

    $request = (new RequestFactory())
        ->createRequest('GET', '/servico');

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->list($request, $response);

    expect($result->getStatusCode())->toBe(200);
});

test('details novo cadastro retorna 200', function () {

    $request = (new RequestFactory())
        ->createRequest('GET', '/servico/detalhes');

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->details($request, $response, []);

    expect($result->getStatusCode())->toBe(200);
});

test('details edicao retorna 200', function () {

    $request = (new RequestFactory())
        ->createRequest('GET', '/servico/detalhes/1');

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->details($request, $response, [
        'id' => 1
    ]);

    expect($result->getStatusCode())->toBe(200);
});

test('insert com dados validos retorna 201', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'Troca de óleo',
            'descricao' => 'Serviço de manutenção',
            'preco' => '120.50',
            'tempo_estimado' => '01:30',
            'ativo' => 'true'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Serviço salvo com sucesso!');
    expect($json['id'])->toBeGreaterThan(0);
});

test('insert sem nome retorna 400', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
});

test('update com dados validos retorna 200', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/update')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'id' => 1,
            'nome' => 'Troca de óleo Premium',
            'descricao' => 'Atualizado',
            'preco' => '180',
            'tempo_estimado' => '02:00',
            'ativo' => true
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->update($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Serviço atualizado com sucesso!');
});

test('update sem id retorna 403', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/update')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'Teste'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->update($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
});

test('update sem nome retorna 400', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/update')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'id' => 1
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->update($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
});

test('delete com id valido retorna sucesso', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/delete')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'id' => 1
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->delete($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Serviço removido com sucesso!');
});

test('delete sem id retorna 403', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/delete')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->delete($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
});

test('listingdata retorna dados', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/servico/listingdata')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'start' => 0,
            'length' => 10,
            'search' => [
                'value' => ''
            ],
            'order' => [
                [
                    'column' => 0,
                    'dir' => 'DESC'
                ]
            ]
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Service())->listingdata($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json)->toHaveKey('recordsTotal');
    expect($json)->toHaveKey('recordsFiltered');
    expect($json)->toHaveKey('data');
    expect($json['data'])->toBeArray();
});
