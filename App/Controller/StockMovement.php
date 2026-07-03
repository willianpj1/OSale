<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;

final class StockMovement extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-stock'), [
                'titulo' => 'Estoque',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'p.id',
            1 => 'p.nome',
            2 => 'p.estoque_atual',
            3 => 'p.estoque_minimo',
        ];

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'p.id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        try {
            $totalRecords = (int) DB::select('COUNT(*)')
                ->from('products', 'p')
                ->where('p.excluido = false')
                ->fetchOne();

            $query = DB::select('p.id, p.nome, p.unidade, p.estoque_atual, p.estoque_minimo')
                ->from('products', 'p')
                ->where('p.excluido = false');

            if (!empty($term)) {
                $query->andWhere($query->expr()->or('p.nome ILIKE :term', 'p.codigo_barra ILIKE :term'))
                    ->setParameter('term', '%' . $term . '%');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $produtos = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = array_map(fn($v) => [
                $v['id'],
                $v['nome'],
                "<span class='" . ((float) $v['estoque_atual'] <= (float) $v['estoque_minimo'] ? 'text-danger fw-bold' : '') . "'>"
                    . number_format((float) $v['estoque_atual'], 3, ',', '.') . " " . $v['unidade'] . "</span>",
                number_format((float) $v['estoque_minimo'], 3, ',', '.'),
                "<div class='d-flex gap-2'>
                    <button type='button' class='btn btn-primary btn-sm px-2 shadow-sm' data-bs-toggle='modal'
                        data-bs-target='#modal-ajuste' data-product-id='{$v['id']}' data-product-nome='{$v['nome']}'
                        data-estoque-atual='{$v['estoque_atual']}'>
                        <i class='bi bi-plus-slash-minus'></i> Ajustar
                    </button>
                </div>",
            ], $produtos);

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function ajustar($request, $response)
    {
        $form       = $request->getParsedBody();
        $productId  = $form['product_id'] ?? null;
        $tipo       = $form['tipo']       ?? null;
        // 'ENTRADA' | 'SAIDA' | 'AJUSTE'
        $quantidade = (float) ($form['quantidade'] ?? 0);
        $observacao = trim($form['observacao'] ?? '');

        if (!$productId) {
            return $this->json($response, ['status' => false, 'msg' => 'Produto não informado', 'id' => 0], 400);
        }
        if (!in_array($tipo, ['ENTRADA', 'SAIDA', 'AJUSTE'], true)) {
            return $this->json($response, ['status' => false, 'msg' => 'Tipo inválido — use ENTRADA, SAIDA ou AJUSTE', 'id' => 0], 400);
        }
        if ($quantidade < 0) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe um valor válido', 'id' => 0], 400);
        }
        if ($tipo !== 'AJUSTE' && $quantidade <= 0) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe uma quantidade maior que zero', 'id' => 0], 400);
        }
        if ($observacao === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o motivo do ajuste', 'id' => 0], 400);
        }

        try {
            $conn = DB::connection();
            $conn->beginTransaction();

            // Lock pessimista — evita que dois ajustes/vendas simultâneas
            // no mesmo produto leiam o mesmo saldo desatualizado.
            $produto = $conn->fetchAssociative(
                'SELECT estoque_atual FROM products WHERE id = :id FOR UPDATE',
                ['id' => (int) $productId]
            );

            if (!$produto) {
                $conn->rollBack();
                return $this->json($response, ['status' => false, 'msg' => 'Produto não encontrado', 'id' => 0], 404);
            }

            $estoqueAnterior = (float) $produto['estoque_atual'];

            if ($tipo === 'AJUSTE') {
                $diferenca = round($quantidade - $estoqueAnterior, 3);

                if ($diferenca === 0.0) {
                    $conn->rollBack();
                    return $this->json($response, ['status' => false, 'msg' => 'O valor informado é igual ao estoque atual', 'id' => 0], 422);
                }

                $direcaoReal      = $diferenca > 0 ? 'ENTRADA' : 'SAIDA';
                $quantidadeReal   = abs($diferenca);
                $estoquePosterior = $quantidade;
            } else {
                if ($tipo === 'SAIDA' && $quantidade > $estoqueAnterior) {
                    $conn->rollBack();
                    return $this->json($response, ['status' => false, 'msg' => 'Quantidade maior que o estoque disponível', 'id' => 0], 422);
                }

                $direcaoReal      = $tipo;
                $quantidadeReal   = $quantidade;
                $estoquePosterior = $tipo === 'ENTRADA'
                    ? $estoqueAnterior + $quantidade
                    : $estoqueAnterior - $quantidade;
            }

            $conn->insert('stock_movements', [
                'product_id'        => (int) $productId,
                'tipo'              => $direcaoReal,
                'origem'            => 'AJUSTE_MANUAL',
                'quantidade'        => $quantidadeReal,
                'estoque_anterior'  => $estoqueAnterior,
                'estoque_posterior' => $estoquePosterior,
                'observacao'        => $observacao,
                'criado_por'        => $_SESSION['user']['id'] ?? null,
                'criado_em'         => $this->now(),
            ], [
                'tipo'   => ParameterType::STRING,
                'origem' => ParameterType::STRING,
            ]);
            // trigger fn_apply_stock_movement atualiza products.estoque_atual

            $conn->commit();

            return $this->json($response, ['status' => true, 'msg' => 'Estoque ajustado com sucesso!', 'id' => (int) $productId], 200);
        } catch (\Throwable $e) {
            if (isset($conn) && $conn->isTransactionActive()) {
                $conn->rollBack();
            }
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao ajustar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
