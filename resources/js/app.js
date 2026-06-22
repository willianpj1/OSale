import '../css/app.css'
import './components/theme.js'

// 1. jQuery — importa explicitamente, expõe global ANTES de tudo
import $ from 'jquery'
window.$ = $
window.jQuery = $

// 2. Bootstrap 5
import * as bootstrap from 'bootstrap'
window.bootstrap = bootstrap

// 3. DataTables — depende de $.fn, precisa vir depois do jQuery global
import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'
import 'datatables.net-staterestore-bs5'

// 4. Select2 — registra $.fn.select2 na instância global
//    Passa window.jQuery explicitamente para garantir que o plugin
//    fica registrado no mesmo $ que os page bundles vão usar
import select2 from 'select2'
select2(window.jQuery)

// 5. jQuery Validate
import 'jquery-validation'
import 'jquery-validation/dist/localization/messages_pt_BR.js'
import 'jquery-validation/dist/localization/methods_pt.js'

// 6. SweetAlert2
import Swal from 'sweetalert2'
window.Swal = Swal

// 7. Inputmask
import Inputmask from 'inputmask'
window.Inputmask = Inputmask.default ?? Inputmask

// 8. Flatpickr
import flatpickr from 'flatpickr'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'
flatpickr.localize(Portuguese)
window.flatpickr = flatpickr

// 9. ECharts
import * as echarts from 'echarts'
window.echarts = echarts