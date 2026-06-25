import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id     = document.getElementById('id');
const Insert = document.getElementById('insert');
const Preco  = document.getElementById('preco');

// ── Máscara de preço ──────────────────────────────────────────────────────────

if (Preco) {
    Inputmask("currency", {
        radixPoint:     ",",
        prefix:         "R$ ",
        autoGroup:      true,
        groupSeparator: ".",
        rightAlign:     false,
        onBeforeMask: (value) => String(value).replace(".", ","),
    }).mask(Preco);
}

// ── Salvar serviço ────────────────────────────────────────────────────────────

async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Corrija os erros antes de salvar.',
            timer: 3000,
            timerProgressBar: true,
        });
        $('button').prop('disabled', false);
        return;
    }

    // Limpa máscara do preço antes de enviar
    const requests = new Requests();
    requests.setForm('form');

    if (Preco && Preco.inputmask) {
        const valorPuro = Preco.inputmask.unmaskedvalue().replace(',', '.');
        requests.body.set('preco', valorPuro);
    }

    try {
        const response = Action.value !== 'e'
            ? await requests.post('/servico/inserir')
            : await requests.post('/servico/atualizar');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Erro ao salvar.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg,
                timer: 3000,
                timerProgressBar: true,
            }).then(() => { window.location.href = '/servico/lista'; });
            return;
        }

        Action.value = 'e';
        Id.value     = response.id;
        window.history.pushState({}, '', `${window.location.origin}/servico/detalhes/${response.id}`);

        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg,
            timer: 3000,
            timerProgressBar: true,
        }).then(() => { window.location.reload(); });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        $('button').prop('disabled', false);
    }
}

Insert.addEventListener('click', applyChanges);