/**
 * Select2 — Wrapper para inicialização de campos Select2 com AJAX.
 *
 * Responsabilidades:
 *  - Inicializar / reinicializar o plugin Select2 sobre um <select>
 *  - Gerenciar ciclo de vida (destroy antes de re-init)
 *  - Expor leitura e escrita de valor de forma encapsulada
 *  - Oferecer destruição explícita para limpeza de memória
 *
 * Dependências esperadas (globais):
 *  - jQuery  (window.jQuery ou window.$)
 *  - Select2 ($.fn.select2)
 *
 * Formato esperado do backend:
 *  { results: [{ id: "1", text: "Item 1" }, ...], pagination?: { more: boolean } }
 *
 * ════════════════════════════════════════
 *  USO
 * ════════════════════════════════════════
 *
 * Básico:
 *   const dept = new Select2('#departamento');
 *   dept.init('/api/departamentos');
 *
 * Placeholders customizados:
 *   const dept = new Select2('#departamento');
 *   dept.init('/api/departamentos', {
 *       placeholder: 'Selecione o departamento',
 *       searchPlaceholder: 'Buscar departamento...',
 *   });
 *
 * Dentro de modal Bootstrap:
 *   const cat = new Select2('#categoria');
 *   cat.init('/api/categorias', {
 *       dropdownParent: '#modalProduto',
 *       placeholder: 'Selecione a categoria',
 *   });
 *
 * Com processResults customizado:
 *   const prod = new Select2('#produto');
 *   prod.init('/api/produtos', {
 *       processResults: (data) => ({
 *           results: data.items.map(i => ({ id: i.codigo, text: i.nome }))
 *       }),
 *   });
 *
 * Ler / definir valor:
 *   console.log(dept.val);
 *   dept.val = '5';
 *
 * Ouvir eventos:
 *   dept.on('select2:select', (e) => {
 *       console.log('Selecionou:', e.params.data);
 *   });
 *
 * Destruir:
 *   dept.destroy();
 */
export default class Select2 {

    /** @type {jQuery} Referência cacheada ao elemento <select> */
    #$el;

    /** @type {boolean} Indica se o Select2 está ativo neste elemento */
    #initialized = false;

    /** @type {string} Placeholder do campo de busca dentro do dropdown */
    #searchPlaceholder = '';

    /** @type {Function|null} Handler bound do evento open (para remoção limpa) */
    #onOpenHandler = null;

    /**
     * @param {string|HTMLElement} selector — Seletor CSS ou elemento DOM do <select>.
     */
    constructor(selector) {
        // ── Valida dependências ──
        if (typeof jQuery === 'undefined') {
            throw new Error('[Select2] jQuery não encontrado. Inclua jQuery antes deste script.');
        }
        if (typeof jQuery.fn.select2 === 'undefined') {
            throw new Error('[Select2] Plugin Select2 não encontrado. Inclua select2.min.js após jQuery.');
        }

        // ── Resolve o elemento ──
        if (!selector) {
            throw new Error('[Select2] O parâmetro "selector" é obrigatório.');
        }

        this.#$el = jQuery(selector);

        if (this.#$el.length === 0) {
            throw new Error(`[Select2] Elemento não encontrado: "${selector}"`);
        }
    }

    /**
     * Inicializa (ou reinicializa) o Select2 com busca AJAX.
     *
     * @param {string} url — Endpoint que retorna os dados.
     * @param {Object} [options={}] — Configurações opcionais.
     * @param {string|null}   [options.dropdownParent=null]             — Seletor do container pai (necessário em modais).
     * @param {string}        [options.placeholder='Pesquisar...']      — Placeholder do campo <select>.
     * @param {string}        [options.searchPlaceholder='Digite para pesquisar...'] — Placeholder do campo de busca do dropdown.
     * @param {number}        [options.minimumInputLength=1]            — Mínimo de caracteres antes de disparar AJAX.
     * @param {boolean}       [options.allowClear=true]                 — Exibe botão de limpar seleção.
     * @param {boolean}       [options.cache=true]                      — Cache de resultados AJAX.
     * @param {number}        [options.delay=300]                       — Debounce em ms antes de disparar a requisição.
     * @param {Function|null} [options.processResults=null]             — Função customizada para processar o retorno.
     * @param {Object}        [options.extra={}]                        — Props extras repassadas direto ao Select2.
     * @returns {this} Para encadeamento.
     */
    init(url, options = {}) {
        if (!url) {
            throw new Error('[Select2] O parâmetro "url" é obrigatório.');
        }

        // ── Destrói instância anterior se existir ──
        this.destroy();

        // ── Desestrutura opções com defaults seguros ──
        const {
            dropdownParent = null,
            placeholder = 'Pesquisar...',
            searchPlaceholder = 'Digite para pesquisar...',
            minimumInputLength = 1,
            allowClear = true,
            cache = true,
            delay = 300,
            processResults = null,
            extra = {},
        } = options;

        // Armazena para uso no handler de open
        this.#searchPlaceholder = searchPlaceholder;

        // ── Monta configuração ──
        const config = {
            theme: 'bootstrap-5',
            language: 'pt-BR',
            placeholder,
            allowClear,
            minimumInputLength,
            selectionCssClass: 'select2--large',
            dropdownCssClass: 'select2--large',
            ajax: {
                type: 'POST',
                url,
                dataType: 'json',
                delay,
                cache,
            },
            ...extra,
        };

        // processResults customizado (se fornecido)
        if (typeof processResults === 'function') {
            config.ajax.processResults = processResults;
        }

        // dropdownParent — resolve para elemento jQuery
        if (dropdownParent) {
            const $parent = jQuery(dropdownParent);
            if ($parent.length === 0) {
                console.warn(`[Select2] dropdownParent "${dropdownParent}" não encontrado. Ignorando.`);
            } else {
                config.dropdownParent = $parent;
            }
        }

        // ── Inicializa ──
        this.#$el.select2(config);

        // ── Placeholder + autofocus no campo de busca ──
        // Handler vinculado A ESTA INSTÂNCIA (não global).
        // Usa a API interna do Select2 para localizar o dropdown
        // correto DESTA instância, sem afetar outros na página.
        this.#onOpenHandler = () => {
            const $dropdown = this.#$el.data('select2').$dropdown;
            const $search = $dropdown.find('.select2-search__field');

            if ($search.length) {
                $search.attr('placeholder', this.#searchPlaceholder);
                $search[0].focus();
            }
        };

        this.#$el.on('select2:open', this.#onOpenHandler);
        this.#initialized = true;

        return this;
    }

    /**
     * Retorna o valor atual selecionado.
     * @returns {string|string[]}
     */
    get val() {
        return this.#$el.val();
    }

    /**
     * Define o valor programaticamente e dispara change.
     * @param {string|string[]} value
     */
    set val(value) {
        this.#$el.val(value).trigger('change');
    }

    /**
     * Escuta eventos do Select2 (select2:select, select2:clear, etc.).
     * @param {string} event — Nome do evento.
     * @param {Function} handler — Callback.
     * @returns {this}
     */
    on(event, handler) {
        this.#$el.on(event, handler);
        return this;
    }

    /**
     * Remove listener.
     * @param {string} event
     * @param {Function} [handler]
     * @returns {this}
     */
    off(event, handler) {
        this.#$el.off(event, handler);
        return this;
    }

    /**
     * Abre o dropdown programaticamente.
     * @returns {this}
     */
    open() {
        if (this.#initialized) this.#$el.select2('open');
        return this;
    }

    /**
     * Fecha o dropdown programaticamente.
     * @returns {this}
     */
    close() {
        if (this.#initialized) this.#$el.select2('close');
        return this;
    }

    /**
     * Destrói a instância Select2 e limpa event listeners.
     * Seguro para chamar mesmo se não inicializado.
     * @returns {this}
     */
    destroy() {
        if (this.#initialized) {
            // Remove o handler de open DESTA instância
            if (this.#onOpenHandler) {
                this.#$el.off('select2:open', this.#onOpenHandler);
                this.#onOpenHandler = null;
            }
            this.#$el.select2('destroy');
            this.#initialized = false;
        }
        return this;
    }

    /**
     * Indica se o Select2 está ativo neste elemento.
     * @returns {boolean}
     */
    get isInitialized() {
        return this.#initialized;
    }

    /**
     * Retorna o elemento jQuery encapsulado (escape hatch).
     * @returns {jQuery}
     */
    get element() {
        return this.#$el;
    }
}