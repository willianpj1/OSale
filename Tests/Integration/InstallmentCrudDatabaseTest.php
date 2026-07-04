<?php

declare(strict_types=1);

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

/**
 * Sufixo "Installment" nos helpers pra evitar colisão de "Cannot redeclare
 * function" com helpers homônimos em outros arquivos de teste
 * (ServiceOrderCrudDatabaseTest.php já tem criarPaymentTermTeste, por ex).
 */
function criarPaymentTermTesteInstallment(string $sufixo = ''): int
{
    DB::connection()->insert('payment_terms', [
        'codigo'        => substr(uniqid(), -2),
        'titulo'        => 'Forma Teste Installment' . $sufixo,
        'atalho'        => 'FTI',
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    return (int) DB::connection()->lastInsertId();
}

function limparInstallmentTeste(int $idPagamento): void
{
    DB::connection()->delete('installment', ['id_pagamento' => $idPagamento]);
    DB::connection()->delete('payment_terms', ['id' => $idPagamento]);
}

// ─────────────────────────────────────────────────────────────────────────────

test('insere uma parcela vinculada a uma condição de pagamento', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-insert');

    DB::connection()->insert('installment', [
        'id_pagamento' => $idPagamento,
        'parcela'      => 1,
        'intervalo'    => 30,
    ]);

    $id = (int) DB::connection()->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    $row = DB::select('*')
        ->from('installment')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($row)->not->toBeFalse();
    expect((int) $row['id_pagamento'])->toBe($idPagamento);
    expect((int) $row['parcela'])->toBe(1);
    expect((int) $row['intervalo'])->toBe(30);

    limparInstallmentTeste($idPagamento);
});

test('lista parcelas de uma condição de pagamento ordenadas por id', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-list');

    // Insere fora de ordem de "parcela" pra garantir que o ORDER BY é por id,
    // igual o controller faz (orderBy('id', 'ASC')), não por número da parcela.
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 2, 'intervalo' => 60]);
    $primeiroId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 1, 'intervalo' => 30]);
    $segundoId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 3, 'intervalo' => 90]);
    $terceiroId = (int) DB::connection()->lastInsertId();

    $rows = DB::select('*')
        ->from('installment')
        ->where('id_pagamento = :id')
        ->setParameter('id', $idPagamento, ParameterType::INTEGER)
        ->orderBy('id', 'ASC')
        ->fetchAllAssociative();

    expect($rows)->toHaveCount(3);
    expect((int) $rows[0]['id'])->toBe($primeiroId);
    expect((int) $rows[1]['id'])->toBe($segundoId);
    expect((int) $rows[2]['id'])->toBe($terceiroId);

    limparInstallmentTeste($idPagamento);
});

test('lista vazia quando a condição de pagamento não tem parcelas cadastradas', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-vazio');

    $rows = DB::select('*')
        ->from('installment')
        ->where('id_pagamento = :id')
        ->setParameter('id', $idPagamento, ParameterType::INTEGER)
        ->orderBy('id', 'ASC')
        ->fetchAllAssociative();

    expect($rows)->toBeEmpty();

    limparInstallmentTeste($idPagamento);
});

test('remove uma parcela pelo id', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-delete');

    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 1, 'intervalo' => 0]);
    $id = (int) DB::connection()->lastInsertId();

    DB::connection()->delete('installment', ['id' => $id]);

    $row = DB::select('*')
        ->from('installment')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($row)->toBeFalse();

    limparInstallmentTeste($idPagamento);
});

test('remove todas as parcelas de uma condição de pagamento sem afetar outras', function () {

    $idPagamentoA = criarPaymentTermTesteInstallment('-cascata-a');
    $idPagamentoB = criarPaymentTermTesteInstallment('-cascata-b');

    DB::connection()->insert('installment', ['id_pagamento' => $idPagamentoA, 'parcela' => 1, 'intervalo' => 0]);
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamentoA, 'parcela' => 2, 'intervalo' => 30]);
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamentoB, 'parcela' => 1, 'intervalo' => 0]);

    // Simula uma "limpeza total" das parcelas de uma condição — como o
    // controller faria removendo item a item, mas aqui em lote pra testar
    // que não afeta parcelas de outra condição de pagamento.
    DB::connection()->delete('installment', ['id_pagamento' => $idPagamentoA]);

    $restantesA = DB::select('*')->from('installment')
        ->where('id_pagamento = :id')
        ->setParameter('id', $idPagamentoA, ParameterType::INTEGER)
        ->fetchAllAssociative();

    $restantesB = DB::select('*')->from('installment')
        ->where('id_pagamento = :id')
        ->setParameter('id', $idPagamentoB, ParameterType::INTEGER)
        ->fetchAllAssociative();

    expect($restantesA)->toBeEmpty();
    expect($restantesB)->toHaveCount(1);

    limparInstallmentTeste($idPagamentoA);
    limparInstallmentTeste($idPagamentoB);
});

test('permite intervalo zero para parcela à vista', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-avista');

    DB::connection()->insert('installment', [
        'id_pagamento' => $idPagamento,
        'parcela'      => 1,
        'intervalo'    => 0,
    ]);

    $id = (int) DB::connection()->lastInsertId();

    $row = DB::select('intervalo')
        ->from('installment')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect((int) $row['intervalo'])->toBe(0);

    limparInstallmentTeste($idPagamento);
});

test('conta corretamente o número de parcelas cadastradas (usado como max_parcelas)', function () {

    $idPagamento = criarPaymentTermTesteInstallment('-count');

    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 1, 'intervalo' => 0]);
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 2, 'intervalo' => 30]);
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 3, 'intervalo' => 60]);
    DB::connection()->insert('installment', ['id_pagamento' => $idPagamento, 'parcela' => 4, 'intervalo' => 90]);

    // Mesma query usada pelo ServiceOrder::finalize() pra validar max_parcelas
    $total = (int) DB::select('COUNT(*)')
        ->from('installment')
        ->where('id_pagamento = :id')
        ->setParameter('id', $idPagamento, ParameterType::INTEGER)
        ->fetchOne();

    expect($total)->toBe(4);

    limparInstallmentTeste($idPagamento);
});