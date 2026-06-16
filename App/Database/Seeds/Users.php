<?php

declare(strict_types=1);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class Users extends AbstractSeed
{
    public function run(): void
    {
        $faker = Factory::create('pt_BR');

        $table = $this->table('users');

        // Limpa a tabela
         $this->execute('DELETE FROM customers');

        $batchSize = 100;
        $total = 100;

        for ($i = 0; $i < $total; $i += $batchSize) {

            $batch = [];

            for ($j = 0; $j < $batchSize && ($i + $j) < $total; $j++) {

                $salario = $faker->randomFloat(4, 1412, 30000);

                $batch[] = [
                    'nome' => $faker->firstName(),
                    'sobrenome' => $faker->lastName(),
                    'senha' => password_hash('123456', PASSWORD_BCRYPT),
                    'salario' => $salario,
                    'rg' => $faker->rg(),
                    'cpf' => $faker->cpf(false),
                    'ativo' => $faker->boolean(85),
                    'administrador' => $faker->boolean(10),
                    'data_cadastro' => $faker->dateTimeBetween('-2 years', 'now')
                        ->format('Y-m-d H:i:s'),
                    'data_atualizacao' => $faker->dateTimeBetween('-1 year', 'now')
                        ->format('Y-m-d H:i:s'),
                ];
            }

            $table->insert($batch)->saveData();
        }

        echo "Seed de usuários concluída com sucesso!" . PHP_EOL;
    }
}
