<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class Products extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $batchSize = 5;
        $total = 5;


        // Busca fornecedores existentes
        $suppliers = $this->fetchAll('SELECT id FROM suppliers');
        $supplierIds = array_column($suppliers, 'id');

        if (empty($supplierIds)) {
            throw new RuntimeException(
                'Nenhum fornecedor encontrado. Execute o SuppliersSeeder primeiro.'
            );
        }

        $table = $this->table('products');

        $unidades = ['un', 'kg', 'm', 'l'];

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $precoCompra = $faker->randomFloat(2, 1, 5000);
                $margemLucro = $faker->randomFloat(2, 10, 150);

                $precoVenda = round(
                    $precoCompra * (1 + ($margemLucro / 100)),
                    2
                );

                $batch[] = [
                    'supplier_id' => $faker->randomElement($supplierIds),

                    'nome' => $faker->words(
                        $faker->numberBetween(2, 5),
                        true
                    ),

                    // índice UNIQUE
                    'codigo_barra' => $faker->unique()->ean13(),

                    'unidade' => $faker->randomElement($unidades),

                    'descricao' => $faker->optional(0.8)->sentence(),

                    'preco_compra' => $precoCompra,

                    'margem_lucro' => $margemLucro,

                    'preco_venda' => $precoVenda,

                    'estoque_atual' => $faker->randomFloat(
                        3,
                        0,
                        1000
                    ),

                    'estoque_minimo' => $faker->randomFloat(
                        3,
                        1,
                        100
                    ),

                    'ativo' => $faker->boolean(95),

                    'excluido' => $faker->boolean(2),

                    'criado_em' => $faker
                        ->dateTimeBetween('-3 years', '-1 day')
                        ->format('Y-m-d H:i:s'),

                    'atualizado_em' => $faker
                        ->dateTimeBetween('-1 year', 'now')
                        ->format('Y-m-d H:i:s'),
                ];
            }

            $table->insert($batch)->saveData();

            // Evita crescimento infinito do cache do unique()
            $faker->unique(true);
        }

        echo "Seed de produtos concluída com sucesso!" . PHP_EOL;
    }
}