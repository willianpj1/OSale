import DataTables from '../components/data-tables.js';

const table = DataTables.SetId('table-stock-movements').setRequestVariables([]).post('/estoque/movimentacoes/listingdata');