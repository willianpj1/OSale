// Calcula o preço de venda com base no preço de compra, impostos, custos operacionais 
// e margem de lucro
export class SellingPriceCalculator {
    #purchasePrice = 0;  // Preço de compra do produto (valor absoluto)
    #totalTax = 0;       // Percentual total de impostos (ex: 15 para 15%)
    #profitMargin = 0;   // Percentual de margem de lucro desejada (ex: 20 para 20%)
    #operatingCost = 0;  // Percentual de custo operacional (ex: 10 para 10%)
    // Instancia a classe via método estático (padrão Factory)
    static create() {
        return new SellingPriceCalculator();
    }
    // Define o preço de compra e retorna a instância para encadeamento
    addPurchasePrice(purchasePrice) {
        this.#purchasePrice = purchasePrice;
        return this;
    }
    // Define o percentual de impostos e retorna a instância para encadeamento
    addTotalTax(totalTax) {
        this.#totalTax = totalTax;
        return this;
    }
    // Define o percentual de margem de lucro e retorna a instância para encadeamento
    addProfitMargin(profitMargin) {
        this.#profitMargin = profitMargin;
        return this;
    }
    // Define o percentual de custo operacional e retorna a instância para encadeamento
    addOperatingCost(operatingCost) {
        this.#operatingCost = operatingCost;
        return this;
    }
    // Calcula e retorna o preço de venda, impostos, custo operacional e margem de lucro
    getData() {

        //1. Validação de tipos
        const inputs = {
            purchasePrice: this.#purchasePrice || 0,
            totalTax: this.#totalTax || 0,
            profitMargin: this.#profitMargin || 0,
            operatingCost: this.#operatingCost || 0,
        };
        for (const [field, value] of Object.entries(inputs)) {
            if (typeof value !== 'number' || !Number.isFinite(value)) {
                throw new TypeError(`"${field}" deve ser um número finito. Recebido: ${value}`);
            }
        }

        //2. Validação do valor de compra e percentuais
        if (inputs.purchasePrice <= 0) {
            throw new RangeError(`Preço de compra deve ser maior que zero. Recebido: ${inputs.purchasePrice}`);
        }

        const percentuais = { totalTax: inputs.totalTax, profitMargin: inputs.profitMargin, operatingCost: inputs.operatingCost };

        for (const [field, value] of Object.entries(percentuais)) {
            if (value < 0) {
                throw new RangeError(`"${field}" não pode ser negativo. Recebido: ${value}`);
            }
            if (value >= 100) {
                throw new RangeError(`"${field}" não pode ser igual ou superior a 100%. Recebido: ${value}`);
            }
        }
        //3. Conversão para decimal com precisão segura
        // Divisão por 100 via multiplicação por 0.01 para minimizar erro de ponto flutuante
        const taxRate = Math.round(inputs.totalTax * 1e10) / 1e12;
        const marginRate = Math.round(inputs.profitMargin * 1e10) / 1e12;
        const operatingRate = Math.round(inputs.operatingCost * 1e10) / 1e12;

        //4. Validação do divisor (soma dos percentuais >= 100%)
        const totalRate = taxRate + marginRate + operatingRate;
        const divisor = 1 - totalRate;

        if (divisor <= 0) {
            const somaPercent = (totalRate * 100).toFixed(4);
            throw new RangeError(
                `A soma dos percentuais (${somaPercent}%) deve ser menor que 100%. ` +
                `Reduza imposto, margem ou custo operacional.`
            );
        }

        // Margem mínima de segurança: divisor muito próximo de zero distorce o preço
        const DIVISOR_MINIMO = 0.01; // soma dos percentuais <= 99%
        if (divisor < DIVISOR_MINIMO) {
            throw new RangeError(
                `A soma dos percentuais é alta demais (acima de 99%), ` +
                `o que tornaria o preço de venda inviável.`
            );
        }

        //5. Cálculo principal
        const sellingPrice = inputs.purchasePrice / divisor;

        //6. Decomposição das parcelas
        const valorImposto = sellingPrice * taxRate;
        const valorCustoOperacional = sellingPrice * operatingRate;
        const valorMargem = sellingPrice * marginRate;
        //7. Arredondamento monetário (2 casas) com correção de resíduo
        //   Garante que: arredondado(imposto) + arredondado(custo) + arredondado(margem)
        //              + precoCusto == arredondado(precoVenda)  (sem centavo perdido)
        const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;

        const sellingPriceR = round2(sellingPrice);
        const valorImpostoR = round2(valorImposto);
        const valorCustoOperacionalR = round2(valorCustoOperacional);
        const valorMargemR = round2(valorMargem);

        // Resíduo de arredondamento: absorvido na margem de lucro (convenção contábil)
        const somaComponentes = round2(
            inputs.purchasePrice + valorImpostoR + valorCustoOperacionalR + valorMargemR
        );
        const residuo = round2(sellingPriceR - somaComponentes);
        const valorMargemFinal = round2(valorMargemR + residuo);

        //8. Retorno
        return {
            valor_venda_sugerido: sellingPriceR,
            valor_total_imposto: valorImpostoR,
            valor_custo_operacional: valorCustoOperacionalR,
            valor_margem_lucro: valorMargemFinal,
        };

    }
}