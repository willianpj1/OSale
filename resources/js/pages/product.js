import Requests from "../components/requests.js";
import Validate from "../components/validate.js";
import { SellingPriceCalculator } from "../components/selling-price-calculator.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');
const PrecoCompra = document.getElementById('preco_compra');
const MargemLucro = document.getElementById('margem_lucro');
const PrecoVenda = document.getElementById('preco_venda');
const TotalImposto = document.getElementById('total_imposto');
// ── Máscaras ──────────────────────────────────────────────────────────────────

const inputmaskConfig = {
    radixPoint: ",",
    inputtype: "text",
    prefix: "R$ ",
    autoGroup: true,
    groupSeparator: ".",
    rightAlign: false,
    onBeforeMask: function (value) {
        return String(value).replace(".", ",");
    },
};

if (PrecoCompra) Inputmask("currency", inputmaskConfig).mask(PrecoCompra);
if (PrecoVenda) Inputmask("currency", inputmaskConfig).mask(PrecoVenda);
if (TotalImposto) {
    Inputmask({
        alias: 'decimal',
        radixPoint: ',',
        digits: 2,
        min: 0,
        max: 99.99,
        rightAlign: false,
        placeholder: '',
        suffix: ' %',
    }).mask(TotalImposto);
}
if (MargemLucro) {
    Inputmask({
        alias: 'decimal',
        radixPoint: ',',
        digits: 2,
        min: 0,
        max: 99.99,
        rightAlign: false,
        placeholder: '',
        suffix: ' %',
    }).mask(MargemLucro);
}

// ── Calculadora de Preço de Venda ─────────────────────────────────────────────

function calcularPrecoVenda() {
    if (!PrecoCompra || !MargemLucro || !PrecoVenda) return;

    const compraRaw = PrecoCompra.inputmask
        ? PrecoCompra.inputmask.unmaskedvalue().replace(',', '.')
        : PrecoCompra.value.replace(',', '.');

    const margemRaw = MargemLucro.inputmask
        ? MargemLucro.inputmask.unmaskedvalue().replace(',', '.')
        : MargemLucro.value.replace(',', '.');

    const impostoRaw = TotalImposto?.inputmask
        ? TotalImposto.inputmask.unmaskedvalue().replace(',', '.')
        : (TotalImposto?.value ?? '0').replace(',', '.');

    const compra = parseFloat(compraRaw) || 0;
    const margem = parseFloat(margemRaw) || 0;
    const imposto = parseFloat(impostoRaw) || 0;

    if (compra <= 0) return; // só precisa do preço de compra

    try {
        const resultado = SellingPriceCalculator.create()
            .addPurchasePrice(compra)
            .addTotalTax(imposto)
            .addProfitMargin(margem)
            .getData();

        if (PrecoVenda.inputmask) {
            PrecoVenda.inputmask.remove();
        }
        PrecoVenda.value = resultado.valor_venda_sugerido.toFixed(2).replace('.', ',');
        Inputmask("currency", inputmaskConfig).mask(PrecoVenda);

    } catch (e) {
        // percentuais inválidos — silencioso
    }
}
if (PrecoCompra) PrecoCompra.addEventListener('input', calcularPrecoVenda);
if (MargemLucro) MargemLucro.addEventListener('input', calcularPrecoVenda);
if (TotalImposto) TotalImposto.addEventListener('input', calcularPrecoVenda);  // ← novo

// ── Helpers ───────────────────────────────────────────────────────────────────

function limparInputsParaEnvio(requests) {
    ['preco_venda', 'preco_compra'].forEach(id => {
        const campo = document.getElementById(id);
        if (campo && campo.inputmask) {
            const valorPuro = campo.inputmask.unmaskedvalue().replace(',', '.');
            requests.body.set(id, valorPuro);
        }
    });

    if (MargemLucro && MargemLucro.inputmask) {
        const valorMargem = MargemLucro.inputmask.unmaskedvalue().replace(',', '.');
        requests.body.set('margem_lucro', valorMargem);
    }

    if (TotalImposto && TotalImposto.inputmask) {  // ← novo
        const valorImposto = TotalImposto.inputmask.unmaskedvalue().replace(',', '.');
        requests.body.set('total_imposto', valorImposto);
    }
}
// ── Salvar Produto ────────────────────────────────────────────────────────────

async function applyChanges() {
    const requests = new Requests();
    requests.setForm('form');

    $('button, input, select, textarea').prop('disabled', true);

    limparInputsParaEnvio(requests);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Por favor, corrija os erros no formulário antes de salvar.', timer: 3000, timerProgressBar: true });
        $('button, input, select, textarea').prop('disabled', false);
        return;
    }

    try {
        const response = Action.value !== 'e'
            ? await requests.post('/produto/inserir')
            : await requests.post('/produto/atualizar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        const redirectUrl = `${window.location.origin}/produto/detalhes/${response.id}`;

        if (Action.value === 'e') {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
                .then(() => { window.location.href = '/produto/lista'; });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 3000, timerProgressBar: true })
            .then(() => { window.location.href = redirectUrl; });

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        $('button, input, select, textarea').prop('disabled', false);
    }
}

if (Insert) {
    Insert.addEventListener('click', applyChanges);
}