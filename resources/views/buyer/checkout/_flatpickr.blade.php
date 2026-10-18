@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fpLocale = (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.ru) ? flatpickr.l10ns.ru : 'default';
    const pickers = [];

    document.querySelectorAll('.js-flatpickr').forEach((el) => {
        pickers.push(flatpickr(el, {
            locale: fpLocale,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd.m.Y',
            minDate: 'today',
            allowInput: false,
            disableMobile: true,
            altInputClass: 'form-control-brand',
        }));
    });

    const syncDisabledUi = () => {
        pickers.forEach((fp) => {
            const disabled = fp.input.disabled;
            if (disabled) {
                fp.close();
                if (fp.altInput) fp.altInput.disabled = true;
            } else if (fp.altInput) {
                fp.altInput.disabled = false;
            }
        });
    };

    document.querySelectorAll('.js-delivery-method').forEach((radio) => {
        radio.addEventListener('change', () => {
            setTimeout(syncDisabledUi, 0);
        });
    });

    setTimeout(syncDisabledUi, 0);
});
</script>
@endpush
