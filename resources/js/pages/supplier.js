import { Modal } from 'bootstrap';
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id     = document.getElementById('id');
const Cnpj   = document.getElementById('cnpj');
const Insert = document.getElementById('insert');

// ── Inicialização de Máscaras ─────────────────────────────────────────────────

if (Cnpj) {
    Inputmask({
        mask: ['999.999.999-99', '99.999.999/9999-99'],
        keepStatic: true,
        clearIncomplete: false
    }).mask(Cnpj);
}

const cepInput = document.getElementById('a-cep');
if (cepInput) {
    Inputmask('99999-999').mask(cepInput);
}

const telInput = document.getElementById('c-contato');
if (telInput) {
    Inputmask({
        mask: ['(99) 9999-9999', '(99) 99999-9999'],
        keepStatic: true
    }).mask(telInput);
}

// ── Salvar Fornecedor ─────────────────────────────────────────────────────────

async function applyChanges() {
    // 1. Monta o FormData ANTES de desabilitar
    const requests = new Requests();
    requests.setForm('form');

    // 2. Agora desabilita
    $('button, input, checkbox').prop('disabled', true);

    let originalCnpjValue = '';
    if (Cnpj) {
        originalCnpjValue = Cnpj.value;
        Cnpj.value = Cnpj.inputmask ? Cnpj.inputmask.unmaskedvalue() : originalCnpjValue;
        requests.body.set('cnpj', Cnpj.value);
    }

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Corrija os erros antes de salvar.', timer: 3000, timerProgressBar: true });
        if (Cnpj) Cnpj.value = originalCnpjValue;
        $('button, input, checkbox').prop('disabled', false);
        return;
    }

    try {
        const response = Action.value !== 'e'
            ? await requests.post('/fornecedor/inserir')
            : await requests.post('/fornecedor/atualizar');

        if (!response.status) {
            if (Cnpj) Cnpj.value = originalCnpjValue;
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        const redirectUrl = `${window.location.origin}/fornecedor/detalhes/${response.id}`;

        if (Action.value === 'e') {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
                .then(() => { window.location.href = '/fornecedor/lista'; });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
            .then(() => { window.location.href = redirectUrl; });

    } catch (error) {
        if (Cnpj) Cnpj.value = originalCnpjValue;
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button, input, checkbox').prop('disabled', false);
    }
}

if (Insert) {
    Insert.addEventListener('click', applyChanges);
}

// ── Endereços ─────────────────────────────────────────────────────────────────

const elModalAddress = document.getElementById('modal-address');
const modalAddress = elModalAddress ? new Modal(elModalAddress) : null;

document.getElementById('btn-add-address')?.addEventListener('click', () => {
    document.getElementById('a-nome').value        = '';
    document.getElementById('a-cep').value         = '';
    document.getElementById('a-logradouro').value  = '';
    document.getElementById('a-numero').value      = '';
    document.getElementById('a-complemento').value = '';
    document.getElementById('a-bairro').value      = '';
    document.getElementById('a-cidade').value      = '';
    document.getElementById('a-estado').value      = '';
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
        nome:        document.getElementById('a-nome').value,
        cep:         document.getElementById('a-cep').value,
        logradouro,
        numero:      document.getElementById('a-numero').value,
        complemento: document.getElementById('a-complemento').value,
        bairro:      document.getElementById('a-bairro').value,
        cidade:      document.getElementById('a-cidade').value,
        estado:      document.getElementById('a-estado').value,
        principal:   document.getElementById('a-principal').checked ? 'true' : 'false',
    });

    try {
        const res  = await fetch(`/fornecedor/${Id.value}/endereco`, { method: 'POST', body });
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
            const res  = await fetch(`/fornecedor/endereco/${addressId}`, { method: 'POST' });
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
    document.getElementById('c-tipo').value        = 'telefone';
    document.getElementById('c-nome').value        = '';
    document.getElementById('c-contato').value     = '';
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
        tipo:      document.getElementById('c-tipo').value,
        nome:      document.getElementById('c-nome').value,
        contato,
        principal: document.getElementById('c-principal').checked ? 'true' : 'false',
    });

    try {
        const res  = await fetch(`/fornecedor/${Id.value}/contato`, { method: 'POST', body });
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
            const res  = await fetch(`/fornecedor/contato/${contactId}`, { method: 'POST' });
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