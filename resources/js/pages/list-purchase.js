import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const table = DataTables.SetId('table-purchase').setRequestVariables([]).post('/compras/listingdata');

// ── Excluir ───────────────────────────────────────────────────────────────────

async function deletePurchase() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/compras/excluir');
        return response;
    } catch (error) {
        return { status: false, msg: error?.message || 'Erro ao excluir compra.' };
    }
}

async function ShowModal(id) {
    Id.value = id;
    Swal.fire({
        title: 'Atenção!',
        text: 'Deseja realmente excluir esta compra?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deletePurchase();
            if (!response || !response.status) {
                Swal.fire({
                    title: 'Erro!',
                    text: response?.msg || 'Não foi possível excluir esta compra.',
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }
            Swal.fire({
                title: 'Removida!',
                text: 'Compra excluída com sucesso.',
                icon: 'success',
                timer: 2000,
                timerProgressBar: true,
            }).then(() => {
                table.ajax.reload();
            });
        }
    });
}

window.ShowModal = ShowModal;