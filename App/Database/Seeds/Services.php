<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class Services extends AbstractSeed
{
    public function run(): void
    {
        $table = $this->table('services');

        $services = [
            [
                'nome' => 'Formatação e instalação do Windows',
                'descricao' => 'Backup, formatação e instalação completa do sistema operacional.',
                'preco' => 180.00,
                'tempo_estimado' => '3 horas',
            ],
            [
                'nome' => 'Limpeza preventiva notebook',
                'descricao' => 'Limpeza interna, troca de pasta térmica e testes.',
                'preco' => 150.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Troca de tela notebook',
                'descricao' => 'Substituição da tela danificada.',
                'preco' => 250.00,
                'tempo_estimado' => '1 hora',
            ],
            [
                'nome' => 'Troca de teclado notebook',
                'descricao' => 'Substituição do teclado defeituoso.',
                'preco' => 180.00,
                'tempo_estimado' => '1 hora',
            ],
            [
                'nome' => 'Upgrade SSD',
                'descricao' => 'Substituição do HD por SSD com migração de dados.',
                'preco' => 120.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Upgrade memória RAM',
                'descricao' => 'Instalação e configuração de memória RAM.',
                'preco' => 80.00,
                'tempo_estimado' => '30 min',
            ],
            [
                'nome' => 'Limpeza de vírus',
                'descricao' => 'Remoção de malware e otimização do sistema.',
                'preco' => 120.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Recuperação de dados',
                'descricao' => 'Tentativa de recuperação de arquivos perdidos.',
                'preco' => 350.00,
                'tempo_estimado' => '1 dia',
            ],
            [
                'nome' => 'Diagnóstico técnico',
                'descricao' => 'Análise completa do equipamento.',
                'preco' => 50.00,
                'tempo_estimado' => '30 min',
            ],
            [
                'nome' => 'Troca de bateria notebook',
                'descricao' => 'Substituição da bateria.',
                'preco' => 100.00,
                'tempo_estimado' => '30 min',
            ],
            [
                'nome' => 'Instalação de impressora',
                'descricao' => 'Instalação e configuração completa.',
                'preco' => 80.00,
                'tempo_estimado' => '1 hora',
            ],
            [
                'nome' => 'Manutenção de impressora',
                'descricao' => 'Limpeza e revisão geral.',
                'preco' => 180.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Configuração de rede',
                'descricao' => 'Configuração de roteadores e infraestrutura básica.',
                'preco' => 200.00,
                'tempo_estimado' => '3 horas',
            ],
            [
                'nome' => 'Configuração Mikrotik',
                'descricao' => 'Configuração avançada de roteadores Mikrotik.',
                'preco' => 350.00,
                'tempo_estimado' => '4 horas',
            ],
            [
                'nome' => 'Manutenção de servidor',
                'descricao' => 'Diagnóstico e correção de problemas em servidores.',
                'preco' => 500.00,
                'tempo_estimado' => '1 dia',
            ],
            [
                'nome' => 'Instalação de Linux',
                'descricao' => 'Instalação e configuração do sistema Linux.',
                'preco' => 180.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Instalação do pacote Office',
                'descricao' => 'Instalação e configuração dos aplicativos.',
                'preco' => 50.00,
                'tempo_estimado' => '30 min',
            ],
            [
                'nome' => 'Troca de conector DC Jack',
                'descricao' => 'Reparo da alimentação do notebook.',
                'preco' => 220.00,
                'tempo_estimado' => '2 horas',
            ],
            [
                'nome' => 'Reparo de placa mãe',
                'descricao' => 'Diagnóstico e reparo eletrônico.',
                'preco' => 650.00,
                'tempo_estimado' => '3 dias',
            ],
            [
                'nome' => 'Troca de pasta térmica',
                'descricao' => 'Aplicação de pasta térmica premium.',
                'preco' => 70.00,
                'tempo_estimado' => '30 min',
            ],
        ];

        foreach ($services as &$service) {
            $service['ativo'] = true;
            $service['excluido'] = false;
            $service['criado_em'] = date('Y-m-d H:i:s');
            $service['atualizado_em'] = date('Y-m-d H:i:s');
        }

        $table->insert($services)->saveData();

        echo "Seed de serviços concluída com sucesso!" . PHP_EOL;
    }
}