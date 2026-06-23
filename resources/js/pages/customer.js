import { Modal } from 'bootstrap';
import FindCompany from "../components/find-company.js";
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Cnpj = document.getElementById('numeroDocumento');
const Insert = document.getElementById('insert');
Inputmask({ mask: ['999.999.999-99', '99.999.999/9999-99'], keepStatic: true }).mask("#numeroDocumento");
Inputmask({ mask: ['99/99/9999'] }).mask("#dataRegistro");
/*$('#dataRegistro').flatpickr({
    enableTime: false,
    dateFormat: "d/m/Y",
    locale: "pt"
});*/


// ── Salvar cliente ────────────────────────────────────────────────────────────
// ── Máscaras de Entrada do Formulário (Usando Inputmask) ──────────────────────
async function applyChanges() {
    $('button').prop('disabled', true);
    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Por favor, corrija os erros no formulário antes de salvar.`,
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }
    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/cliente/insert') :
            await requests.setForm('form').post('/cliente/update');
        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar os dados do cliente.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }
        const baseUrl = window.location.origin;
        const redirectUrl = `${baseUrl}/cliente/detalhes/${response.id}`;
        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Dados do cliente alterados com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/cliente/lista';
            });
            return;
        }
        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', redirectUrl);
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Cliente salvo com sucesso!',
            timer: 3000,
            timerProgressBar: true,
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
        $('button, input, checkbox').prop('disabled', false);
    } finally {
        $('button, input, checkbox').prop('disabled', false);
    }
}

Cnpj.addEventListener('blur', async () => {
    if (Cnpj.value.trim() === '' || Cnpj.value.replace(/\D/g, '').length < 14) {
        return;
    }
    const findCompany = new FindCompany({ cnpjField: 'numeroDocumento', cnaeValue: 'cnae', cnaeSearch: 'codigoAtividadeEconomica' })
    await findCompany.FindCompanyData();
});

Insert.addEventListener('click', async () => {
    await applyChanges();
});

// ── Endereços ─────────────────────────────────────────────────────────────────
const elModalAddress = document.getElementById('modal-address');
const modalAddress = elModalAddress ? new Modal(elModalAddress) : null;

document.getElementById('a-cep').addEventListener('blur', async () => {
    const request = new Requests();
    try {
        const cep = document.getElementById('a-cep').value.replace('-', '').replace('.', '')
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();
        document.getElementById('a-logradouro').value = data.logradouro ?? '';
        document.getElementById('a-bairro').value = data.bairro ?? '';
        document.getElementById('a-cidade').value = data.localidade ?? '';
        document.getElementById('a-estado').value = data.uf ?? '';

        // foca no número pra o usuário completar
        document.getElementById('a-numero').focus();

    } catch (error) {
        
        console.error('Erro ao buscar CEP:', err);

    }
});


document.getElementById('btn-add-address')?.addEventListener('click', () => {
    document.getElementById('a-nome').value = '';
    document.getElementById('a-cep').value = '';
    document.getElementById('a-logradouro').value = '';
    document.getElementById('a-numero').value = '';
    document.getElementById('a-complemento').value = '';
    document.getElementById('a-bairro').value = '';
    document.getElementById('a-cidade').value = '';
    document.getElementById('a-estado').value = '';
    document.getElementById('a-principal').checked = false;
    modalAddress.show();
});

document.getElementById('btn-save-address')?.addEventListener('click', async () => {
    const logradouro = document.getElementById('a-logradouro').value.trim();
    if (!logradouro) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Logradouro é obrigatório.',
            timer: 2000,
            timerProgressBar: true
        });
        return;
    }

    const body = new URLSearchParams({
        nome: document.getElementById('a-nome').value,
        cep: document.getElementById('a-cep').value,
        logradouro,
        numero: document.getElementById('a-numero').value,
        complemento: document.getElementById('a-complemento').value,
        bairro: document.getElementById('a-bairro').value,
        cidade: document.getElementById('a-cidade').value,
        estado: document.getElementById('a-estado').value,
        principal: document.getElementById('a-principal').checked ? 'true' : 'false',
    });

    try {
        const res = await (`/cliente/${Id.value}/endereco`, { method: 'POST', body });
        const data = await res.json();

        if (!data.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.msg,
                timer: 3000,
                timerProgressBar: true
            });
            return;
        }

        modalAddress.hide();
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: data.msg,
            timer: 2000,
            timerProgressBar: true
        })
            .then(() => { window.location.reload(); });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: e.message,
            timer: 3000,
            timerProgressBar: true
        });
    }
});

async function deleteAddress(addressId) {
    Swal.fire({
        title: 'Atenção!',
        text: 'Deseja remover este endereço?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Remover'
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        try {
            const res = await fetch(`/cliente/endereco/${addressId}`, { method: 'POST' });
            const data = await res.json();
            if (!data.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.msg, timer: 3000, timerProgressBar: true });
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Removido!',
                text: data.msg,
                timer: 2000,
                timerProgressBar: true
            })
                .then(() => { window.location.reload(); });

        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: e.message,
                timer: 3000,
                timerProgressBar: true
            });
        }
    });
}

window.deleteAddress = deleteAddress;

// ── Contatos ──────────────────────────────────────────────────────────────────

const elModalContact = document.getElementById('modal-contact');
const modalContact = elModalContact ? new Modal(elModalContact) : null;

document.getElementById('btn-add-contact')?.addEventListener('click', () => {
    document.getElementById('c-tipo').value = 'telefone';
    document.getElementById('c-nome').value = '';
    document.getElementById('c-contato').value = '';
    document.getElementById('c-principal').checked = false;
    modalContact.show();
});

document.getElementById('btn-save-contact')?.addEventListener('click', async () => {
    const contato = document.getElementById('c-contato').value.trim();
    if (!contato) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Contato é obrigatório.', timer: 2000, timerProgressBar: true });
        return;
    }

    const body = new URLSearchParams({
        tipo: document.getElementById('c-tipo').value,
        nome: document.getElementById('c-nome').value,
        contato,
        principal: document.getElementById('c-principal').checked ? 'true' : 'false',
    });

    try {
        const res = await fetch(`/cliente/${Id.value}/contato`, { method: 'POST', body });
        const data = await res.json();

        if (!data.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        modalContact.hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: data.msg, timer: 2000, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
    }
});

async function deleteContact(contactId) {
    Swal.fire({
        title: 'Atenção!', text: 'Deseja remover este contato?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
        confirmButtonText: 'Remover'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const res = await fetch(`/cliente/contato/${contactId}`, { method: 'POST' });
            const data = await res.json();
            if (!data.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.msg, timer: 3000, timerProgressBar: true });
                return;
            }
            Swal.fire({ icon: 'success', title: 'Removido!', text: data.msg, timer: 2000, timerProgressBar: true })
                .then(() => { window.location.reload(); });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
        }
    });
}

window.deleteContact = deleteContact;