<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('Insert com dados validos retorna 201 status true', function () {
    $request = (new RequestFactory())
        ->createRequest('POST', '/cliente/insert')
        ->withHeader('Content-type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao' => 'willian',
            'nomeLegal' => 'pereira',
            'numeroDocumento' => '123.123.123-12',
            'registroSecundario' => '123456',
            'dataRegistro' => '12/12/2012',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Customer())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);
    #Capturamos o código de resposta caso seja 201 significa que o cadastro 
    #Foi criado. 
    expect($result->getStatusCode())->toBe(201);

    expect($json['msg'])->toContain('Salvo com sucesso!');

    expect($json['status'])->toBeTrue();
});