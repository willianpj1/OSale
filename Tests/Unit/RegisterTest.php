<?php

declare(strict_types=1);

use App\Controller\Register;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('store sem dados retorna 400', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/register/store')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new Register())->store($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
    expect($json['message'])->toContain('Preencha todos os campos');
});
