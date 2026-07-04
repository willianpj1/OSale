<?php

declare(strict_types=1);

use App\Controller\Purchase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('update sem id retorna 403', function () {
    $request = (new RequestFactory())->createRequest('POST', '/compras/update')->withHeader('Content-Type', 'application/x-www-form-urlencoded')->withParsedBody([]);
    $response = (new ResponseFactory())->createResponse();
    $result = (new Purchase())->update($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('ID não informado');
});
