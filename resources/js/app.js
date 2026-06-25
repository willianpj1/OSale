import '../css/app.css'
import './components/theme.js'

// ORDEM É CONTRATO — não reordenar sem análise de dependências.

// [1] jQuery global — PRIMEIRO. Todos os plugins abaixo dependem
import './jquery-global.js'

// [2] Bootstrap 5 JS — vanilla JS, sem jQuery.
import * as bootstrap from 'bootstrap'
window.bootstrap = bootstrap

// [6] DataTables — requer jQuery (carregado em [1]).
import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'
import 'datatables.net-staterestore-bs5'

// [7] Select2 — requer jQuery (carregado em [1]).
import select2 from 'select2'
select2(window, window.jQuery)

// [9] jQuery Validation — requer jQuery (carregado em [1]).
import 'jquery-validation'
import 'jquery-validation/dist/localization/messages_pt_BR.js'
import 'jquery-validation/dist/localization/methods_pt.js'

// [10] SweetAlert2 — exposto em window para uso nos templates Twig. Uso: Swal.fire({ title: 'Confirmar?', icon: 'warning' })
import Swal from 'sweetalert2'
window.Swal = Swal

// [11] Inputmask — exposto em window para mascaras de campos. new Inputmask('99/99/9999').mask(document.getElementById('data'))
import Inputmask from 'inputmask'
window.Inputmask = Inputmask.default ?? Inputmask

// [12] Flatpickr — exposto em window com locale PT-BR aplicado globalmente. Uso: flatpickr('#campo-data', { dateFormat: 'd/m/Y' })
import flatpickr from 'flatpickr'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'
flatpickr.localize(Portuguese)
window.flatpickr = flatpickr

// [13] Apache ECharts — exposto em window para gráficos. Uso: echarts.init(document.getElementById('grafico'))
import * as echarts from 'echarts'
window.echarts = echarts