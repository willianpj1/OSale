import * as bootstrap from 'bootstrap';
import Requests from "../components/requests.js";

let deleteId = null;
let tipoSelecionado = 'ENTRADA';

const modalDelete = new bootstrap.Modal(document.getElementById('modal-delete'));
const modalAjusteEl = document.getElementById('modal-ajuste');
const modalAjuste = new bootstrap.Modal(modalAjusteEl);

$('#table-products').DataTable({
    processing: true,
    serverSide: true,
    ajax: { url: '/produto/listingdata', type: 'POST' },
    columns: [
        { data: 0 },
        { data: 1 },
        { data: 2 },
        { data: 3, orderable: false },
        { data: 4 },
        { data: 5 },
        { data: 6 },
        { data: 7, orderable: false },
    ],
    order: [[0, 'desc']],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
});

window.ShowModal = function (id) {
    deleteId = id;
    modalDelete.show();
};

document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
    if (!deleteId) return;

    const params = new URLSearchParams();
    params.append('id', deleteId);

    try {
        const res = await fetch('/produto/excluir', { method: 'POST', body: params });
        const response = await res.json();

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        modalDelete.hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 2500, timerProgressBar: true })
            .then(() => { $('#table-products').DataTable().ajax.reload(null, false); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    }
});

// ── Ajuste de estoque ────────────────────────────────────────────────────────

function setTipo(tipo) {
    tipoSelecionado = tipo;
    document.querySelectorAll('#modal-ajuste [data-tipo]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tipo === tipo);
    });

    const label = document.getElementById('ajuste-quantidade-label');
    label.textContent = tipo === 'AJUSTE' ? 'Novo estoque (valor final)' : 'Quantidade';
}

document.querySelectorAll('#modal-ajuste [data-tipo]').forEach(btn => {
    btn.addEventListener('click', () => setTipo(btn.dataset.tipo));
});

// Delegação: os botões de ajuste são criados dinamicamente pelo DataTables a cada reload
$('#table-products tbody').on('click', '.btn-ajustar-estoque', function () {
    document.getElementById('ajuste-product-id').value = this.dataset.productId;
    document.getElementById('ajuste-product-nome').value = this.dataset.productNome;
    document.getElementById('ajuste-estoque-atual').value = this.dataset.estoqueAtual;
    document.getElementById('ajuste-quantidade').value = '';
    document.getElementById('ajuste-observacao').value = '';
    setTipo('ENTRADA');
    modalAjuste.show();
});

document.getElementById('btn-confirmar-ajuste').addEventListener('click', async () => {
    const productId = document.getElementById('ajuste-product-id').value;
    const quantidade = document.getElementById('ajuste-quantidade').value;
    const observacao = document.getElementById('ajuste-observacao').value.trim();

    if (quantidade === '' || parseFloat(quantidade) < 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe um valor válido.', timer: 2500, timerProgressBar: true });
        return;
    }
    if (!observacao) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe o motivo do ajuste.', timer: 2500, timerProgressBar: true });
        return;
    }

    $('#modal-ajuste button').prop('disabled', true);

    try {
        const params = new URLSearchParams();
        params.append('product_id', productId);
        params.append('tipo', tipoSelecionado);
        params.append('quantidade', quantidade);
        params.append('observacao', observacao);

        const requests = new Requests();
        const response = await requests.setBody(params).post('/estoque/ajustar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        modalAjuste.hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 2500, timerProgressBar: true })
            .then(() => { $('#table-products').DataTable().ajax.reload(null, false); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('#modal-ajuste button').prop('disabled', false);
    }
});