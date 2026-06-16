<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class SalesItens extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $sales = $this->fetchAll(
            'SELECT id, status FROM sales'
        );

        $products = $this->fetchAll(
            'SELECT id, nome, preco_venda FROM products'
        );

        $services = $this->fetchAll(
            'SELECT id, nome, preco FROM services'
        );

        if (empty($sales)) {
            throw new RuntimeException(
                'Nenhuma venda encontrada.'
            );
        }

        if (empty($products)) {
            throw new RuntimeException(
                'Nenhum produto encontrado.'
            );
        }

        if (empty($services)) {
            throw new RuntimeException(
                'Nenhum serviço encontrado.'
            );
        }

        $table = $this->table('sale_items');

        $batch = [];

        foreach ($sales as $sale) {

            // Vendas canceladas não possuem itens
            if ($sale['status'] === 'cancelada') {
                continue;
            }

            $quantidadeItens = $faker->numberBetween(1, 8);

            for ($i = 0; $i < $quantidadeItens; $i++) {

                $tipo = $faker->randomElement([
                    'produto',
                    'servico'
                ]);

                if ($tipo === 'produto') {

                    $product = $products[
                        array_rand($products)
                    ];

                    $quantidade = $faker->randomFloat(
                        3,
                        1,
                        20
                    );

                    $precoUnitario = (float)
                        $product['preco_venda'];

                    $desconto = $faker->randomFloat(
                        2,
                        0,
                        $precoUnitario * 0.10
                    );

                    $subtotal = round(
                        ($quantidade * $precoUnitario)
                        - $desconto,
                        2
                    );

                    $batch[] = [
                        'sale_id' => $sale['id'],
                        'tipo' => 'produto',
                        'service_id' => null,
                        'product_id' => $product['id'],
                        'descricao' => $product['nome'],
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'desconto_item' => $desconto,
                        'subtotal' => $subtotal,
                        'criado_em' => $faker
                            ->dateTimeBetween('-2 years', 'now')
                            ->format('Y-m-d H:i:s'),
                    ];

                } else {

                    $service = $services[
                        array_rand($services)
                    ];

                    $precoUnitario = (float)
                        $service['preco'];

                    $desconto = $faker->randomFloat(
                        2,
                        0,
                        $precoUnitario * 0.10
                    );

                    $subtotal = round(
                        $precoUnitario - $desconto,
                        2
                    );

                    $batch[] = [
                        'sale_id' => $sale['id'],
                        'tipo' => 'servico',
                        'service_id' => $service['id'],
                        'product_id' => null,
                        'descricao' => $service['nome'],
                        'quantidade' => 1,
                        'preco_unitario' => $precoUnitario,
                        'desconto_item' => $desconto,
                        'subtotal' => $subtotal,
                        'criado_em' => $faker
                            ->dateTimeBetween('-2 years', 'now')
                            ->format('Y-m-d H:i:s'),
                    ];
                }
            }

            if (count($batch) >= 1000) {
                $table->insert($batch)->saveData();
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $table->insert($batch)->saveData();
        }

        echo "Seed de itens de venda concluída com sucesso!" . PHP_EOL;
    }
}