<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('preRegister com dados válidos retorna 200 status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/cadastro')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome'      => 'willian',
            'sobrenome' => 'pereira',
            'cpf'       => '123.123.123-20',
            'rg'        => '123457',
            'senha'     => '1234',
            'email'     => 'pereira@pereira.con',
            'telefone'  => '69999999998',
        ]);


    $response = (new ResponseFactory())->createResponse();

    $result = (new \App\Controller\Register())->preRegister($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);
    #Capturamos o código de resposta caso seja 201 significa que o cadastro 
    #Foi criado. 
    expect($result->getStatusCode())->toBe(201);

    expect($json['msg'])->toContain('Pré-cadastro realizado com sucesso!');

    expect($json['status'])->toBeTrue();
});
