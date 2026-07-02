<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class InstallmentSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['PaymentTermsSeeder'];
    }

    /**
     * PIX, Dinheiro e Débito: 1 parcela única (à vista, intervalo 0).
     * Crédito: 12 linhas (parcela 1 a 12), todas com o mesmo id_pagamento —
     * o front-end (modal de finalizar OS) usa essas linhas pra montar o
     * seletor "1x, 2x, 3x... 12x" e o back-end (calcularParcelas) divide o
     * valor total pela quantidade de parcelas escolhida.
     */
    public function run(): void
    {
        $terms = $this->fetchAll(
            "SELECT id, codigo FROM payment_terms WHERE codigo IN ('pix', 'dinheiro', 'debito', 'credito')"
        );

        if (empty($terms)) {
            return;
        }

        $table = $this->table('installment');
        $data = [];

        foreach ($terms as $term) {
            $idPagamento = (int) $term['id'];

            $jaTem = $this->fetchRow(
                'SELECT id FROM installment WHERE id_pagamento = ' . $idPagamento
            );

            if ($jaTem) {
                continue; // já tem parcela(s) cadastrada(s), não duplica
            }

            if ($term['codigo'] === 'credito') {
                for ($parcela = 1; $parcela <= 12; $parcela++) {
                    $data[] = [
                        'id_pagamento' => $idPagamento,
                        'parcela'      => $parcela,
                        'intervalo'    => 30 * $parcela, // vencimento a cada 30 dias
                    ];
                }
            } else {
                $data[] = [
                    'id_pagamento' => $idPagamento,
                    'parcela'      => 1,
                    'intervalo'    => 0,
                ];
            }
        }

        if (!empty($data)) {
            $table->insert($data)->saveData();
        }
    }
}