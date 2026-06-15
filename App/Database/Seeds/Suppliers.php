<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class Suppliers extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $table = $this->table('suppliers');

        $batchSize = 1000;
        $total = 1000;

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $batch[] = [
                    'nome' => $faker->company(),

                    // UNIQUE
                    'cnpj' => $faker->unique()->cnpj(false),

                    'cep' => $faker->postcode(),

                    'logradouro' => $faker->streetName(),

                    'numero' => (string) $faker->numberBetween(1, 9999),

                    'complemento' => $faker->optional(0.30)->secondaryAddress(),

                    'bairro' => $faker->citySuffix(),

                    'cidade' => $faker->city(),

                    'estado' => strtoupper(
                        $faker->randomElement([
                            'AC','AL','AP','AM','BA','CE',
                            'DF','ES','GO','MA','MT','MS',
                            'MG','PA','PB','PR','PE','PI',
                            'RJ','RN','RS','RO','RR','SC',
                            'SP','SE','TO'
                        ])
                    ),

                    'observacoes' => $faker->optional(0.20)->paragraph(),

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

            // limpa cache do unique() a cada lote
            $faker->unique(true);
        }

        echo "Seed de fornecedores concluída com sucesso!" . PHP_EOL;
    }
}