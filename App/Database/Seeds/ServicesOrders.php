<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class ServicesOrders extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $customers = $this->fetchAll('SELECT id FROM customers');
        $users = $this->fetchAll('SELECT id FROM users');

        $customerIds = array_column($customers, 'id');
        $userIds = array_column($users, 'id');

        if (empty($customerIds)) {
            throw new RuntimeException(
                'Nenhum cliente encontrado. Execute CustomersSeeder primeiro.'
            );
        }

        if (empty($userIds)) {
            throw new RuntimeException(
                'Nenhum usuário encontrado. Execute UsersSeeder primeiro.'
            );
        }

        $equipamentos = [
            'Notebook Dell Inspiron 15',
            'Notebook Lenovo ThinkPad',
            'Notebook Acer Aspire 5',
            'Notebook Samsung Book',
            'Desktop Gamer',
            'Desktop Corporativo',
            'Impressora HP LaserJet',
            'Impressora Epson EcoTank',
            'Servidor Dell PowerEdge',
            'Monitor LG 24"',
            'Monitor Samsung 27"',
            'Nobreak SMS',
            'Roteador Mikrotik',
            'Switch TP-Link',
        ];

        $marcas = [
            'Dell',
            'Lenovo',
            'Acer',
            'Samsung',
            'HP',
            'Epson',
            'LG',
            'Asus',
            'Intelbras',
            'Mikrotik',
        ];

        $statuses = [
            'aberta',
            'em_andamento',
            'aguardando_peca',
            'concluida',
            'cancelada',
        ];

        $prioridades = [
            'baixa',
            'normal',
            'alta',
            'urgente',
        ];

        $table = $this->table('service_orders');

        $batchSize = 1000;
        $total = 10000;

        $contadorOs = 1;

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $status = $faker->randomElement($statuses);

                $abertoEm = $faker->dateTimeBetween(
                    '-2 years',
                    'now'
                );

                $concluidoEm = null;

                if ($status === 'concluida') {
                    $concluidoEm = $faker->dateTimeBetween(
                        $abertoEm,
                        'now'
                    )->format('Y-m-d H:i:s');
                }

                $batch[] = [
                    'customer_id' => $faker->randomElement($customerIds),

                    'user_id' => $faker->optional(0.8)
                        ->randomElement($userIds),

                    'criado_por' => $faker->randomElement($userIds),

                    'numero' => sprintf(
                        'OS-2026-%06d',
                        $contadorOs++
                    ),

                    'status' => $status,

                    'prioridade' => $faker->randomElement(
                        $prioridades
                    ),

                    'equipamento' => $faker->randomElement(
                        $equipamentos
                    ),

                    'marca' => $faker->randomElement(
                        $marcas
                    ),

                    'modelo' => strtoupper(
                        $faker->bothify('???-####')
                    ),

                    'numero_serie' => strtoupper(
                        $faker->bothify('SN########')
                    ),

                    'defeito_relatado' => $faker->sentence(),

                    'defeito_constatado' =>
                        $status === 'aberta'
                            ? null
                            : $faker->paragraph(),

                    'observacoes' => $faker->optional(0.4)
                        ->paragraph(),

                    'valor_total' => $status === 'cancelada'
                        ? 0
                        : $faker->randomFloat(2, 50, 5000),

                    'aberto_em' => $abertoEm
                        ->format('Y-m-d H:i:s'),

                    'concluido_em' => $concluidoEm,

                    'excluido' => $faker->boolean(1),

                    'criado_em' => $abertoEm
                        ->format('Y-m-d H:i:s'),

                    'atualizado_em' => $faker
                        ->dateTimeBetween(
                            $abertoEm,
                            'now'
                        )
                        ->format('Y-m-d H:i:s'),
                ];
            }

            $table->insert($batch)->saveData();
        }

        echo "Seed de ordens de serviço concluída com sucesso!" . PHP_EOL;
    }
}