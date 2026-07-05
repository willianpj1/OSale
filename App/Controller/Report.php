<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
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
    /**
     * Contadores simples de Vendas x Ordens de Serviço.
     */
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
    public function reportPrint($request, $response)
    {
        
    }
}