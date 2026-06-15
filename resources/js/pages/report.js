const ChartSale = echarts.init(document.getElementById('resultadoVenda'));
const ChartMarketing = echarts.init(document.getElementById('resultadoMarketing'));

let dataChartsSale = {
    title: {
        text: 'Resultado de Venda'
    },
    tooltip: {},
    legend: {
        data: ['sales']
    },
    xAxis: {
        data: ['Shirts', 'Cardigans', 'Chiffons', 'Pants', 'Heels', 'Socks']
    },
    yAxis: {},
    series: [
        {
            name: 'sales',
            type: 'bar',
            data: [5, 20, 36, 10, 10, 20]
        }
    ]
};

let dataChartMarketing = {
    tooltip: {
        trigger: 'item'
    },
    legend: {
        top: '5%',
        left: 'center'
    },
    series: [
        {
            name: 'Access From',
            type: 'pie',
            radius: ['40%', '70%'],
            avoidLabelOverlap: false,
            padAngle: 5,
            itemStyle: {
                borderRadius: 10
            },
            label: {
                show: false,
                position: 'center'
            },
            emphasis: {
                label: {
                    show: true,
                    fontSize: 40,
                    fontWeight: 'bold'
                }
            },
            labelLine: {
                show: false
            },
            data: [
                { value: 1048, name: 'Mecanismo de busca' },
                { value: 735, name: 'Mensagem pessoal' },
                { value: 580, name: 'E-mail' },
                { value: 484, name: 'Union Ads' },
                { value: 300, name: 'Video Ads' }
            ]
        }
    ]
};

ChartSale.setOption(dataChartsSale);

ChartMarketing.setOption(dataChartMarketing);