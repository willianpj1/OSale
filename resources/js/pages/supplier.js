import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id     = document.getElementById('id');
const Cnpj   = document.getElementById('numeroDocumento');
const Insert = document.getElementById('insert');

// ── Inicialização de Máscaras e Plugins ──────────────────────────────────────

// Aplica a máscara dupla (CPF/CNPJ) usando o Inputmask global do app.js
if (Cnpj) {
    Inputmask({ 
        mask: ['999.999.999-99', '99.999.999/9999-99'], 
        keepStatic: true 
    }).mask(Cnpj);
}

// Máscara e Inicialização do Flatpickr para a Data de Registro
const dataRegistro = document.getElementById('dataRegistro');
if (dataRegistro) {
    Inputmask({ mask: ['99/99/9999'] }).mask(dataRegistro);
    $(dataRegistro).flatpickr({
        enableTime: false,
        dateFormat: "d/m/Y",
        locale: "pt"
    });
}

// ── Salvar Fornecedor (Ações de Insert / Update) ──────────────────────────────

async function applyChanges() {
    // Bloqueia interações para evitar cliques duplicados
    $('button, input, checkbox').prop('disabled', true);

    let originalCnpjValue = '';
    if (Cnpj) {
        originalCnpjValue = Cnpj.value;
        // Envia apenas números limpos para o banco não recusar o INSERT/UPDATE
        Cnpj.value = Cnpj.inputmask ? Cnpj.inputmask.unmaskedvalue() : originalCnpjValue;
    }

    // Validação do formulário
    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Por favor, corrija os erros no formulário antes de salvar.',
            timer: 3000,
            timerProgressBar: true,
        });
        
        // Se a validação falhar, devolve a máscara visual e destrava a tela
        if (Cnpj) Cnpj.value = originalCnpjValue;
        $('button, input, checkbox').prop('disabled', false);
        return;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/fornecedor/insert')
            : await requests.setForm('form').post('/fornecedor/update');

        if (!response.status) {
            // Se o servidor rejeitar, desfaz a limpeza do valor para o usuário ver o campo formatado
            if (Cnpj) Cnpj.value = originalCnpjValue;

            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar os dados do fornecedor.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        const baseUrl = window.location.origin;
        const redirectUrl = `${baseUrl}/fornecedor/detalhes/${response.id}`;

        // Caso seja uma Edição bem-sucedida
        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Dados do fornecedor alterados com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/fornecedor/lista';
            });
            return;
        }

        // Caso seja uma Inserção nova bem-sucedida
        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', redirectUrl);
        
        // Restaura a máscara para a exibição ficar correta
        if (Cnpj) Cnpj.value = originalCnpjValue;

        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Fornecedor salvo com sucesso!',
            timer: 3000,
            timerProgressBar: true,
        });

    } catch (error) {
        // Fallback para erros inesperados
        if (Cnpj) Cnpj.value = originalCnpjValue;

        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        // Garante o desbloqueio dos campos
        $('button, input, checkbox').prop('disabled', false);
    }
}

// ── Eventos de Escuta (Listeners) ──────────────────────────────────────────────

if (Insert) {
    Insert.addEventListener('click', async () => {
        await applyChanges();
    });
}