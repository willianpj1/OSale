<?php

declare(strict_types=1);

use App\Controller\Installment;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('insert sem id_pagamento retorna 403', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/installment/insert')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Installment())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Informe o ID da condição de pagamento');
});
