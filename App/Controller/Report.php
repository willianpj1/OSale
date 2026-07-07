<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use Doctrine\DBAL\ParameterType;
use Dompdf\Dompdf;
use DateTime;
use Exception;

final class Report extends Base
{
    public function report($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('report'), [
                'titulo' => 'Relatórios',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    /**
     * Curva ABC de produtos/serviços, com base no faturamento (sale_items.subtotal).
     */
    public function curvaAbc($request, $response)
    {
        try {
            $rows = DB::query(
                "SELECT
                si.tipo,
                COALESCE(p.nome, sv.nome, si.descricao) AS nome,
                SUM(si.subtotal) AS total
             FROM sale_items si
             INNER JOIN sales s   ON s.id = si.sale_id AND s.excluido = false
             LEFT JOIN products p  ON p.id = si.product_id AND si.tipo = 'produto'
             LEFT JOIN services sv ON sv.id = si.service_id AND si.tipo = 'servico'
             GROUP BY
                si.tipo,
                COALESCE(si.product_id, si.service_id),
                COALESCE(p.nome, sv.nome, si.descricao)
             HAVING SUM(si.subtotal) > 0
             ORDER BY total DESC"
            );
            $totalGeral = array_sum(
                array_map(
                    static fn($r) => (float) $r['total'],
                    $rows
                )
            );
            $acumulado = 0.0;
            $contadores = [
                'A' => 0,
                'B' => 0,
                'C' => 0,
            ];
            $data = [];
            foreach ($rows as $row) {
                $valor = (float) $row['total'];
                $percentual = $totalGeral > 0
                    ? ($valor / $totalGeral) * 100
                    : 0.0;
                $acumulado += $percentual;
                $classe = match (true) {
                    $acumulado <= 80.0 => 'A',
                    $acumulado <= 95.0 => 'B',
                    default            => 'C',
                };

                // Limita a 10 registros por classe
                if ($contadores[$classe] >= 10) {
                    continue;
                }
                $contadores[$classe]++;
                $data[] = [
                    'nome'       => $row['nome'],
                    'tipo'       => $row['tipo'],
                    'valor'      => round($valor, 2),
                    'percentual' => round($percentual, 2),
                    'acumulado'  => round(min($acumulado, 100), 2),
                    'classe'     => $classe,
                ];
                // Se já temos 10 de cada classe, interrompe o loop
                if (
                    $contadores['A'] >= 10 &&
                    $contadores['B'] >= 10 &&
                    $contadores['C'] >= 10
                ) {
                    break;
                }
            }
            return $this->json($response, [
                'status' => true,
                'total'  => round($totalGeral, 2),
                'data'   => $data,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => $e->getMessage(),
            ], 500);
        }
    }
    public function resumo($request, $response)
    {
        try {
            $vendas = (int) DB::queryOne(
                "SELECT COUNT(*) AS total
             FROM sales
             WHERE excluido = false"
            )['total'];
            $ordensAtivas = (int) DB::queryOne(
                "SELECT COUNT(*) AS total
             FROM service_orders
             WHERE excluido = false
               AND status <> 'cancelada'"
            )['total'];
            $ordensCanceladas = (int) DB::queryOne(
                "SELECT COUNT(*) AS total
             FROM service_orders
             WHERE excluido = false
               AND status = 'cancelada'"
            )['total'];
            return $this->json($response, [
                'status' => true,
                'data' => [
                    'vendas'              => $vendas,
                    'ordens_servico'      => $ordensAtivas,
                    'ordens_canceladas'   => $ordensCanceladas,
                ],
            ], 200);
        } catch (Exception $e) {

            return $this->json($response, [
                'status' => false,
                'msg'    => $e->getMessage(),
            ], 500);
        }
    }
    public function compras($request, $response, $args)
    {
        $id = $args['Id'] ?? null;

        if (!$id) {
            return $response->withStatus(404);
        }

        $purchase = DB::select('p.*, s.nome as supplier_nome, s.cnpj as supplier_cnpj')
            ->from('purchases', 'p')
            ->leftJoin('p', 'suppliers', 's', 's.id = p.supplier_id')
            ->where('p.excluido = false')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->fetchAssociative();

        if (!$purchase) {
            return $response->withStatus(404);
        }

        $items = DB::select('pi.*, pr.nome as product_nome')
            ->from('purchase_items', 'pi')
            ->leftJoin('pi', 'products', 'pr', 'pr.id = pi.product_id')
            ->where('pi.purchase_id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->orderBy('pi.id', 'ASC')
            ->fetchAllAssociative();

        try {
            $html = $this->getTwig()->fetch($this->setView('compra-pdf'), [
                'purchase'   => $purchase,
                'items'      => $items,
                'emitido_em' => new DateTime(),
            ]);

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream("compra-{$purchase['numero']}.pdf", [
                'Attachment' => false,
            ]);

            return $response;
        } catch (Exception $e) {
            error_log('[Report::compras] ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }
    public function ordemServico($request, $response, $args)
    {
        $id = $args['Id'] ?? null;

        if (!$id) {
            return $response->withStatus(404);
        }

        $order = DB::select('so.*, c.nome as customer_nome, u.nome as tecnico_nome, u.sobrenome as tecnico_sobrenome')
            ->from('service_orders', 'so')
            ->leftJoin('so', 'customers', 'c', 'c.id = so.customer_id')
            ->leftJoin('so', 'users',     'u', 'u.id = so.user_id')
            ->where('so.excluido = false')
            ->andWhere('so.id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->fetchAssociative();

        if (!$order) {
            return $response->withStatus(404);
        }

        $items = DB::select('soi.*, s.nome as service_nome, p.nome as product_nome')
            ->from('service_order_items', 'soi')
            ->leftJoin('soi', 'services', 's', 's.id = soi.service_id')
            ->leftJoin('soi', 'products', 'p', 'p.id = soi.product_id')
            ->where('soi.service_order_id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->orderBy('soi.id', 'ASC')
            ->fetchAllAssociative();

        // Mesma lógica de split de pagamento que já existe em ServiceOrder::details()
        $paymentSplits = [];

        $splits = DB::select('sop.*, pt.titulo, pt.codigo, pt.atalho')
            ->from('service_order_payments', 'sop')
            ->leftJoin('sop', 'payment_terms', 'pt', 'pt.id = sop.id_pagamento')
            ->where('sop.service_order_id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->orderBy('sop.id', 'ASC')
            ->fetchAllAssociative();

        foreach ($splits as $split) {
            $rows = DB::select('parcela, intervalo')->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', $split['id_pagamento'], ParameterType::INTEGER)
                ->orderBy('parcela', 'ASC')
                ->setMaxResults((int) $split['parcelas'])
                ->fetchAllAssociative();

            $paymentSplits[] = [
                'titulo'       => $split['titulo'] ?: $split['codigo'],
                'parcelas_qtd' => (int) $split['parcelas'],
                'valor'        => (float) $split['valor'],
                'installments' => $this->calcularParcelas($rows, (float) $split['valor']),
            ];
        }

        try {
            $html = $this->getTwig()->fetch($this->setView('service-order-pdf'), [
                'order'         => $order,
                'items'         => $items,
                'paymentSplits' => $paymentSplits,
                'emitido_em'    => new DateTime(),
            ]);

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream("os-{$order['numero']}.pdf", [
                'Attachment' => false,
            ]);

            return $response;
        } catch (Exception $e) {
            error_log('[Report::ordemServico] ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    //------helpers---------------------------------------------------
    private function calcularParcelas(array $rows, float $total): array
    {
        $qtd = count($rows);

        if ($qtd === 0) {
            return [];
        }

        $valorBase   = round($total / $qtd, 2);
        $parcelas    = [];
        $somaParcial = 0.0;

        foreach ($rows as $i => $r) {
            $isUltima = ($i === $qtd - 1);
            $valor = $isUltima ? round($total - $somaParcial, 2) : $valorBase;

            $parcelas[] = [
                'parcela'   => $r['parcela'],
                'intervalo' => $r['intervalo'],
                'valor'     => $valor,
            ];

            $somaParcial += $valor;
        }

        return $parcelas;
    }
}
