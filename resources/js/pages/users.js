import { Modal } from 'bootstrap';
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');
const Cnpj = document.getElementById('cpf_cnpj');


// ── Salvar cliente ────────────────────────────────────────────────────────────
// ── Máscaras de Entrada do Formulário (Usando Inputmask) ──────────────────────

document.addEventListener('DOMContentLoaded', () => {

    // 1. Máscara dinâmica para CPF/CNPJ no mesmo campo
    const cpfCnpjInput = document.getElementById('cpf_cnpj'); // Certifique-se de que o id no HTML seja este
    if (cpfCnpjInput) {
        Inputmask({
            mask: ['999.999.999-99', '99.999.999/9999-99'],
            keepStatic: true, // Evita que a máscara mude agressivamente enquanto digita
            clearIncomplete: false
        }).mask(cpfCnpjInput);
    }

    // 2. Máscara de CEP no modal de endereços
    const cepInput = document.getElementById('a-cep');
    if (cepInput) {
        Inputmask('99999-999').mask(cepInput);
    }

    // 3. Máscara dinâmica para Celular/Telefone no modal de contatos
    const telInput = document.getElementById('c-contato');
    if (telInput) {
        Inputmask({
            mask: ['(99) 9999-9999', '(99) 99999-9999'],
            keepStatic: true
        }).mask(telInput);
    }
});

Cnpj.addEventListener('blur', async () => {
    if (Cnpj.value.trim() === '' || Cnpj.value.replace(/\D/g, '').length < 14) {
        return;
    }
    const findCompany = new FindCompany({ cnpjField: 'cpf_cnpj'})
    await findCompany.FindCompanyData();
});


async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Corrija os erros antes de salvar.', timer: 3000, timerProgressBar: true });
        $('button').prop('disabled', false);
        return;
    }

    const requests = new Requests();
    try {
        const response = Action.value !== 'e'
            ? await requests.setForm('form').post('/usuarios/inserir')
            : await requests.setForm('form').post('/usuarios/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        if (Action.value === 'e') {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
                .then(() => { window.location.href = '/usuarios/lista'; });
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', `${window.location.origin}/usuarios/detalhes/${response.id}`);

        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
            .then(() => { window.location.reload(); });

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

Insert.addEventListener('click', applyChanges);

// ── Endereços ─────────────────────────────────────────────────────────────────

const elModalAddress = document.getElementById('modal-address');
const modalAddress = elModalAddress ? new Modal(elModalAddress) : null;

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
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Logradouro é obrigatório.', timer: 2000, timerProgressBar: true });
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
        const res = await fetch(`/usuarios/${Id.value}/endereco`, { method: 'POST', body });
        const data = await res.json();

        if (!data.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        modalAddress.hide();
        Swal.fire({ icon: 'success', title: 'Sucesso', text: data.msg, timer: 2000, timerProgressBar: true })
            .then(() => { window.location.reload(); });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
    }
});

async function deleteAddress(addressId) {
    Swal.fire({
        title: 'Atenção!', text: 'Deseja remover este endereço?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
        confirmButtonText: 'Remover'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const res = await fetch(`/usuarios/endereco/${addressId}`, { method: 'POST' });
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