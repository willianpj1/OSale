import '../css/app.css'
import './components/theme.js'

// 1. jQuery global — DEVE ser o primeiro import.
import './jquery-global.js'

// 2. Bootstrap 5 — JS bundle inclui Popper internamente
import 'bootstrap'

// DataTables core + extensões com tema Bootstrap 5
import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'
import 'datatables.net-staterestore-bs5'

// Select2 — registra $.fn.select2
import 'select2'

// jQuery Validate — registra $.fn.validate
import 'jquery-validation'
import 'jquery-validation/dist/localization/messages_pt_BR.js'
import 'jquery-validation/dist/localization/methods_pt.js'

// 3. SweetAlert2 — usado como Swal.fire(...) nos arquivos de página
import Swal from 'sweetalert2'
window.Swal = Swal

// 4. Biblioteca de mascaras
import Inputmask from 'inputmask';
window.Inputmask = Inputmask.default ?? Inputmask;

// 5. Biblioteca de calendário
import flatpickr from 'flatpickr'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'
flatpickr.localize(Portuguese)
window.flatpickr = flatpickr