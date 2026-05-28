<?php


declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('insertUsers com dados validos retorna 200 com status true', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'lionel',
            'sobrenome' => 'oanao',
            'cpf' => '753.967.532-09',
            'rg' => '4546676',
            'ativo' => 'true'
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Users())->insert($request, $response);

    $result->getBody()->rewind();


    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Usuário salvo com sucesso!');


});
test('updateUsers com dados validos retorna 201 com status true', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/insert')
        ->withParsedBody([
            'nome'      => 'messiasano',
            'sobrenome' => 'oet',
            'cpf'       => '876.341.423-34',
            'rg'        => '56365432',
            'ativo'     => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->insert($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    $id   = $json['id'];


    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/update')
        ->withParsedBody([
            'id'        => $id,
            'nome'      => 'marcianooet',
            'sobrenome' => 'anaozudo',
            'cpf'       => '876.341.423-34',
            'rg'        => '56365432',
            'ativo'     => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->update($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Usuário alterado com sucesso!');
});

test('updateUsers sem ID retorna 403 com status false', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/update')
        ->withParsedBody([
            'nome' => 'Sem ID',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->update($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Por favor informe o ID do registro');
});


test('deleteUsers com ID valido retorna 200 com status true', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/insert')
        ->withParsedBody([
            'nome'      => 'oliso',
            'sobrenome' => 'ingress',
            'cpf'       => '098.578.766-56',
            'rg'        => '5576633',
            'ativo'     => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->insert($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    $id   = $json['id'];


    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/delete')
        ->withParsedBody(['id' => $id]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->delete($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toContain('Usuário removido com sucesso!');
});

test('deleteUsers sem ID retorna 403 com status false', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/delete')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->delete($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Informe o código do usuário');
});


test('listingdata retorna estrutura correta com status 200', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/listingdata')
        ->withParsedBody([
            'start'  => 0,
            'length' => 10,
            'order'  => [['column' => '0', 'dir' => 'DESC']],
            'search' => ['value' => ''],
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->listingdata($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json)->toHaveKey('recordsTotal');
    expect($json)->toHaveKey('recordsFiltered');
    expect($json)->toHaveKey('data');
    expect($json['data'])->toBeArray();
});

test('listingdata com termo de busca retorna resultado filtrado', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/usuario/listingdata')
        ->withParsedBody([
            'start'  => 0,
            'length' => 10,
            'order'  => [['column' => '1', 'dir' => 'ASC']],
            'search' => ['value' => 'lionel'],
        ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new app\controller\Users())->listingdata($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['recordsFiltered'])->toBeInt();
    expect($json['data'])->toBeArray();
});
