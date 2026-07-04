<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\DB;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\ParameterType;

// Teste 1: conexão com PostgreSQL funciona
test('conexão com PostgreSQL está ativa', function () {
    $conn = Connection::get();

    expect($conn)->toBeInstanceOf(DBALConnection::class);

    $result = $conn->fetchAssociative('SELECT 1 AS ok');

    expect((int) $result['ok'])->toBe(1);
});

// Teste 2: ciclo insert → select → delete funcionam no PostgreSQL
test('insert select e delete funcionam no PostgreSQL', function () {
    $conn = Connection::get();

    // INSERT — cria um serviço temporário para não colidir com dados reais
    $conn->insert('services', [
        'nome'          => 'Serviço Teste DatabaseTest',
        'preco'         => 0.00,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'ativo'    => ParameterType::BOOLEAN,
        'excluido' => ParameterType::BOOLEAN,
    ]);

    $id = (int) $conn->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    // SELECT — confirma que o registro foi salvo
    $service = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($service)->not->toBeFalse();
    expect($service['nome'])->toBe('Serviço Teste DatabaseTest');

    // DELETE — limpa o registro de teste
    $conn->delete('services', ['id' => $id]);

    $serviceRemovido = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($serviceRemovido)->toBeFalse();
});