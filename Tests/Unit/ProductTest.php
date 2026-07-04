<?php

declare(strict_types=1);

use App\Controller\Product;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('insert sem nome retorna 400', function () {
    $request = (new RequestFactory())->createRequest('POST', '/produto/insert')->withHeader('Content-Type', 'application/x-www-form-urlencoded')->withParsedBody([]);
    $response = (new ResponseFactory())->createResponse();
    $result = (new Product())->insert($request, $response);
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);
    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
});
