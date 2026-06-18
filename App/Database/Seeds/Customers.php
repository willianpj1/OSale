<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class Customers extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $table = $this->table('customers');       


        $batchSize = 5;
        $total = 5;

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $tipo = $faker->randomElement(['fisica', 'juridica']);

                $batch[] = [
                    'nome' => $tipo === 'fisica'
                        ? $faker->name()
                        : $faker->company(),

                    'tipo' => $tipo,

                    'cpf_cnpj' => $tipo === 'fisica'
                        ? $faker->cpf(false)
                        : $faker->cnpj(false),

                    'rg_ie' => $tipo === 'fisica'
                        ? $faker->rg()
                        : strtoupper($faker->bothify('###.###.###.###')),

                    'observacoes' => $faker->optional(0.3)->sentence(),

                    'ativo' => $faker->boolean(90),

                    'excluido' => $faker->boolean(5),

                   
                ];
            }

            $table->insert($batch)->saveData();
        }

        echo "Seed de clientes concluída com sucesso!" . PHP_EOL;
    }
}
