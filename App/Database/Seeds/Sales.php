<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class Sales extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $customers = $this->fetchAll('SELECT id FROM customers');
        $users = $this->fetchAll('SELECT id FROM users');
        $serviceOrders = $this->fetchAll('SELECT id FROM service_orders');

        $customerIds = array_column($customers, 'id');
        $userIds = array_column($users, 'id');
        $serviceOrderIds = array_column($serviceOrders, 'id');

        if (empty($userIds)) {
            throw new RuntimeException(
                'Nenhum usuário encontrado. Execute UsersSeeder primeiro.'
            );
        }

        $formasPagamento = [
            'dinheiro',
            'pix',
            'cartao_credito',
            'cartao_debito',
            'transferencia',
        ];

        $statusList = [
            'aberta',
            'finalizada',
            'cancelada',
        ];

        $table = $this->table('sales');

        $batchSize = 1000;
        $total = 10000;
        $contadorVenda = 1;

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $status = $faker->randomElement($statusList);

                $totalProdutos = $faker->randomFloat(2, 0, 3000);
                $totalServicos = $faker->randomFloat(2, 0, 2000);

                $subTotal = $totalProdutos + $totalServicos;

                $desconto = $subTotal > 0
                    ? $faker->randomFloat(2, 0, $subTotal * 0.15)
                    : 0;

                $totalGeral = max(
                    0,
                    round($subTotal - $desconto, 2)
                );

                $criadoEm = $faker
                    ->dateTimeBetween('-2 years', 'now')
                    ->format('Y-m-d H:i:s');

                $batch[] = [
                    // 80% das vendas possuem cliente
                    'customer_id' => !empty($customerIds) && $faker->boolean(80)
                        ? $faker->randomElement($customerIds)
                        : null,

                    'user_id' => $faker->randomElement($userIds),

                    // 25% vinculadas a ordem de serviço
                    'service_order_id' => !empty($serviceOrderIds) && $faker->boolean(25)
                        ? $faker->randomElement($serviceOrderIds)
                        : null,

                    'numero' => sprintf(
                        'VND-2026-%06d',
                        $contadorVenda++
                    ),

                    'status' => $status,

                    'forma_pagamento' => $status === 'cancelada'
                        ? null
                        : $faker->randomElement($formasPagamento),

                    'desconto' => $status === 'cancelada'
                        ? 0
                        : $desconto,

                    'total_servicos' => $status === 'cancelada'
                        ? 0
                        : $totalServicos,

                    'total_produtos' => $status === 'cancelada'
                        ? 0
                        : $totalProdutos,

                    'total_geral' => $status === 'cancelada'
                        ? 0
                        : $totalGeral,

                    'observacoes' => $faker->optional(0.3)
                        ->sentence(),

                    'excluido' => $faker->boolean(1),

                    'criado_em' => $criadoEm,

                    'atualizado_em' => $faker
                        ->dateTimeBetween($criadoEm, 'now')
                        ->format('Y-m-d H:i:s'),
                ];
            }

            $table->insert($batch)->saveData();
        }

        echo "Seed de vendas concluída com sucesso!" . PHP_EOL;
    }
}