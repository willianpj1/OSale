<?php

declare(strict_types=1);

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

test('ciclo CRUD completo na tabela services', function () {

    // 1. INSERT — cria um serviço real no banco
    DB::connection()->insert('services', [
        'nome'           => 'Serviço de Integração Teste',
        'descricao'      => 'Descrição do serviço de teste',
        'preco'          => 99.90,
        'tempo_estimado' => '2h',
        'ativo'          => true,
        'excluido'       => false,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'ativo'    => ParameterType::BOOLEAN,
        'excluido' => ParameterType::BOOLEAN,
    ]);

    $id = (int) DB::connection()->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    // 2. SELECT — busca o serviço recém-criado
    $service = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($service)->not->toBeEmpty();
    expect($service['nome'])->toBe('Serviço de Integração Teste');
    expect($service['descricao'])->toBe('Descrição do serviço de teste');
    expect((float) $service['preco'])->toBe(99.90);
    expect($service['tempo_estimado'])->toBe('2h');
    expect($service['ativo'])->toBeTrue();
    expect($service['excluido'])->toBeFalse();

    // 3. UPDATE — altera o serviço
    DB::connection()->update('services', [
        'nome'          => 'Serviço Alterado',
        'preco'         => 149.90,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id]);

    // Confirma que a alteração persistiu
    $serviceAlterado = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($serviceAlterado['nome'])->toBe('Serviço Alterado');
    expect((float) $serviceAlterado['preco'])->toBe(149.90);

    // 4. SOFT DELETE — marca como excluído (padrão do sistema)
    DB::connection()->update('services', [
        'excluido'      => true,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

    // Confirma que o soft delete funcionou
    $serviceExcluido = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->andWhere('excluido = false')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($serviceExcluido)->toBeFalse();

    // 5. HARD DELETE — remove o registro de teste do banco pra não poluir
    DB::connection()->delete('services', ['id' => $id]);

    $serviceRemovido = DB::select('*')
        ->from('services')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($serviceRemovido)->toBeFalse();
});