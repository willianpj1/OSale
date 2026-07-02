import { Modal } from 'bootstrap';
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import FindCompany from "../components/find-company.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');
const Cnpj = document.getElementById('cpf_cnpj')
const elModal = document.getElementById('modal-address');
const modalAddress = elModal ? new Modal(elModal) : null;
const form = document.getElementById('form-address');



// ── Salvar cliente ────────────────────────────────────────────────────────────
// ── Máscaras de Entrada do Formulário (Usando Inputmask) ──────────────────────

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
            ? await requests.setForm('form').post('/cliente/inserir')
            : await requests.setForm('form').post('/cliente/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        if (Action.value === 'e') {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
                .then(() => { window.location.href = '/cliente/lista'; });
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', `${window.location.origin}/cliente/detalhes/${response.id}`);

        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
            .then(() => { window.location.reload(); });

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button').prop('disabled', false);
    }
}

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
    const findCompany = new FindCompany({ cnpjField: 'cpf_cnpj', cnaeValue: 'cnae', cnaeSearch: 'codigoAtividadeEconomica' })
    const response = await findCompany.FindCompanyData();
    const Ie = response?.estabelecimento?.inscricoes_estaduais[0]?.inscricao_estadual;
    const Nome = response?.estabelecimento?.nome_fantasia ?? response?.razao_social;

    document.getElementById('nome').value = Nome;
    document.getElementById('rg_ie').value = Ie;

});

Insert.addEventListener('click', applyChanges);

// ── Endereços ─────────────────────────────────────────────────────────────────


// Centralizador de alertas para evitar repetição de código
const toast = (icon, title, text, cb) => Swal.fire({ icon, title, text, timer: 2000, timerProgressBar: true }).then(cb);

async function listAddresses() {
    const requests = new Requests();
    try {
        const response = await requests.post(`/cliente/${Id.value}/enderecoslista`);
        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao listar endereços.', timer: 3000, timerProgressBar: true });
            return;
        }
        const addressesContainer = document.getElementById('addresses-container');
        let HTML = '';
        if (response.data.length === 0) {
            addressesContainer.innerHTML = '<p class="text-muted">Nenhum endereço cadastrado.</p>';
            return;
        }
        response.data.forEach(item => {
            HTML += `
                <tr id="address-${item.id}">
                    <td>${item.label}</td>
                    <td>${item.logradouro}</td>
                    <td>${item.numero}</td>
                    <td>${item.bairro}</td>
                    <td>${item.cidade} / ${item.estado}</td>
                    <td>${item.principal ? 'Sim' : 'Não'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(${item.id})">Excluir</button>
                    </td>
                </tr>           
            `;
        });
        addressesContainer.innerHTML = response.html; // Supondo que o backend retorne HTML para os endereços
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    }
}

// 1. Abrir e Limpar Modal
document.getElementById('btn-add-address')?.addEventListener('click', () => {
    form?.reset();
    modalAddress?.show();
});

// 2. Busca Automática de CEP (ViaCEP integrada diretamente)
document.getElementById('a-cep')?.addEventListener('blur', async (e) => {
    const cep = e.target.value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    try {
        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        if (!res.ok) return;

        const data = await res.json();
        if (data.erro) return toast('warning', 'Atenção', 'CEP não encontrado.');

        // Preenche os campos automaticamente usando os IDs existentes
        document.getElementById('a-logradouro').value = data.logradouro ?? '';
        document.getElementById('a-bairro').value = data.bairro ?? '';
        document.getElementById('a-cidade').value = data.localidade ?? '';
        document.getElementById('a-estado').value = data.uf ?? '';

        // Foca no campo número automaticamente para agilizar a digitação
        document.getElementById('a-numero')?.focus();
    } catch (err) {
        console.error("Erro CEP:", err);
    }
});

// 3. Salvar Endereço (Captura automática via FormData)
document.getElementById('btn-save-address')?.addEventListener('click', async () => {
    const requests = new Requests();
    try {
        // O .SetForm('form') já captura todos os inputs e o estado do checkbox sozinho
        const response = await requests.setForm('form-address').post(`/cliente/${Id.value}/endereco`);

        // Correção de 'resData' para 'response'
        if (!response.status) {
            throw new Error(response.msg);
        }

        modalAddress.hide();
        toast('success', 'Sucesso', response.msg);
        await listAddresses();

    } catch (e) {
        toast('error', 'Erro', e.message);
    }
});

// 4. Deletar Endereço
window.deleteAddress = async (addressId) => {
    const confirm = await Swal.fire({ title: 'Atenção!', text: 'Deseja remover este endereço?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Remover' });
    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch(`/cliente/endereco/${addressId}`, { method: 'POST' });
        const resData = await res.json();

        if (!resData.status) throw new Error(resData.msg);
        toast('success', 'Removido!', resData.msg, () => window.location.reload());
    } catch (e) {
        toast('error', 'Erro', e.message);
    }
};


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