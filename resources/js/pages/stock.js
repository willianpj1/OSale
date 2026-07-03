import Requests from "../components/requests.js";

let tipoSelecionado = 'ENTRADA';

$('#table-stock').DataTable({
    processing: true,
    serverSide: true,
    ajax: { url: '/estoque/listagem', type: 'POST' },
    columns: [
        { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4, orderable: false },
    ],
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
});

const modalEl = document.getElementById('modal-ajuste');

modalEl.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    document.getElementById('ajuste-product-id').value = btn.dataset.productId;
    document.getElementById('ajuste-product-nome').value = btn.dataset.productNome;
    document.getElementById('ajuste-estoque-atual').value = btn.dataset.estoqueAtual;
    document.getElementById('ajuste-quantidade').value = '';
    document.getElementById('ajuste-observacao').value = '';
    setTipo('ENTRADA');
});

function setTipo(tipo) {
    tipoSelecionado = tipo;
    document.querySelectorAll('#modal-ajuste [data-tipo]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tipo === tipo);
    });
}

document.querySelectorAll('#modal-ajuste [data-tipo]').forEach(btn => {
    btn.addEventListener('click', () => setTipo(btn.dataset.tipo));
});

document.getElementById('btn-confirmar-ajuste').addEventListener('click', async () => {
    const productId = document.getElementById('ajuste-product-id').value;
    const quantidade = document.getElementById('ajuste-quantidade').value;
    const observacao = document.getElementById('ajuste-observacao').value.trim();

    if (!quantidade || parseFloat(quantidade) <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe uma quantidade válida.', timer: 2500, timerProgressBar: true });
        return;
    }
    if (!observacao) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe o motivo do ajuste.', timer: 2500, timerProgressBar: true });
        return;
    }

    $('#modal-ajuste button').prop('disabled', true);

    try {
        const requests = new Requests();
        const response = await requests.post('/estoque/ajustar', {
            product_id: productId,
            tipo: tipoSelecionado,
            quantidade,
            observacao,
        });

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        bootstrap.Modal.getInstance(modalEl).hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 2500, timerProgressBar: true })
            .then(() => { $('#table-stock').DataTable().ajax.reload(null, false); });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('#modal-ajuste button').prop('disabled', false);
    }
});