import Requests from '../components/requests.js';

const ChartAbc = echarts.init(document.getElementById('chart-curva-abc'));
const ChartResumo = echarts.init(document.getElementById('chart-resumo'));

ChartAbc.showLoading({ text: 'Carregando...' });
ChartResumo.showLoading({ text: 'Carregando...' });

const CORES_CLASSE = {
    A: '#28a745',
    B: '#ffc107',
    C: '#dc3545',
};

async function carregarCurvaAbc() {
    const requests = new Requests();
    try {
        const response = await requests.get('/relatorio/curva-abc');
        if (!response?.status) return;

        const dados = response.data;

        ChartAbc.setOption({
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: (params) => {
                    const item = dados[params[0].dataIndex];
                    return `
                        <strong>${item.nome}</strong><br>
                        Classe: <strong>${item.classe}</strong><br>
                        Valor: R$ ${item.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}<br>
                        % do total: ${item.percentual.toFixed(2)}%<br>
                        Acumulado: ${item.acumulado.toFixed(2)}%
                    `;
                },
            },
            grid: { left: 60, right: 60, bottom: 110, top: 40 },
            xAxis: {
                type: 'category',
                data: dados.map(item => item.nome),
                axisLabel: { rotate: 45, interval: 0, fontSize: 10 },
            },
            yAxis: [
                { type: 'value', name: 'R$', position: 'left' },
                {
                    type: 'value',
                    name: '% acumulado',
                    position: 'right',
                    min: 0,
                    max: 100,
                    axisLabel: { formatter: '{value}%' },
                },
            ],
            dataZoom: [
                { type: 'inside', xAxisIndex: 0 },
                { type: 'slider', xAxisIndex: 0, height: 18, bottom: 70 },
            ],
            series: [
                {
                    name: 'Valor',
                    type: 'bar',
                    yAxisIndex: 0,
                    data: dados.map(item => ({
                        value: item.valor,
                        itemStyle: { color: CORES_CLASSE[item.classe] ?? '#999' },
                    })),
                },
                {
                    name: '% acumulado',
                    type: 'line',
                    yAxisIndex: 1,
                    smooth: true,
                    symbol: 'circle',
                    symbolSize: 6,
                    lineStyle: { color: '#0e0e0e', width: 2 },
                    itemStyle: { color: '#0e0e0e' },
                    data: dados.map(item => item.acumulado),
                    markLine: {
                        symbol: 'none',
                        data: [
                            { yAxis: 80, lineStyle: { color: '#28a745', type: 'dashed' }, label: { formatter: '80%' } },
                            { yAxis: 95, lineStyle: { color: '#ffc107', type: 'dashed' }, label: { formatter: '95%' } },
                        ],
                    },
                },
            ],
        });
    } catch (error) {
        console.error('Erro ao carregar curva ABC:', error);
    } finally {
        ChartAbc.hideLoading();
    }
}

async function carregarResumo() {
    const requests = new Requests();
    try {
        const response = await requests.get('/relatorio/resumo');
        if (!response?.status) return;

        const {
            vendas,
            ordens_servico,
            ordens_canceladas
        } = response.data;

        ChartResumo.setOption({
            tooltip: {
                trigger: 'item'
            },
            legend: {
                bottom: 0
            },
            series: [
                {
                    name: 'Total',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    itemStyle: {
                        borderRadius: 6,
                        borderColor: '#fff',
                        borderWidth: 2
                    },
                    label: {
                        formatter: '{b}\n{c} ({d}%)'
                    },
                    data: [
                        {
                            value: vendas,
                            name: 'Vendas'
                        },
                        {
                            value: ordens_servico,
                            name: 'Ordens de Serviço'
                        },
                        {
                            value: ordens_canceladas,
                            name: 'OS Canceladas'
                        }
                    ]
                }
            ]
        });
    } catch (error) {
        console.error('Erro ao carregar resumo:', error);
    } finally {
        ChartResumo.hideLoading();
    }
}

carregarCurvaAbc();
carregarResumo();

window.addEventListener('resize', () => {
    ChartAbc.resize();
    ChartResumo.resize();
});

