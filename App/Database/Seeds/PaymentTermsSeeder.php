<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class PaymentTermsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $table = $this->table('payment_terms');

        $existentes = $this->fetchAll('SELECT codigo FROM payment_terms');
        $codigosExistentes = array_column($existentes, 'codigo');

        $data = [
            ['codigo' => 'pix',      'titulo' => 'PIX',               'atalho' => 'PIX'],
            ['codigo' => 'dinheiro', 'titulo' => 'Dinheiro',          'atalho' => 'DIN'],
            ['codigo' => 'debito',   'titulo' => 'Cartão de Débito',  'atalho' => 'DEB'],
            ['codigo' => 'credito',  'titulo' => 'Cartão de Crédito', 'atalho' => 'CRED'],
        ];

        $data = array_values(array_filter(
            $data,
            fn($row) => !in_array($row['codigo'], $codigosExistentes, true)
        ));

        if (!empty($data)) {
            $table->insert($data)->saveData();
        }
    }
}