@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('productCatalogLive', (initial, url, intervalSeconds) => ({
        live: initial,
        url,
        intervalSeconds: Number(intervalSeconds) || 0,
        timer: null,
        statusMessage: null,
        isUnavailable: initial.unavailable_in_region ?? false,

        start() {
            if (!this.url || this.intervalSeconds <= 0) {
                return;
            }
            this.timer = setInterval(() => this.refresh(), this.intervalSeconds * 1000);
        },

        async refresh() {
            try {
                const response = await fetch(this.url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (response.status === 404) {
                    const payload = await response.json();
                    this.statusMessage = payload.message || 'Товар больше недоступен.';
                    this.live.is_purchasable = false;
                    this.live.can_add_to_order = false;
                    clearInterval(this.timer);
                    return;
                }

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (!payload.visible) {
                    this.statusMessage = payload.message || 'Товар больше недоступен.';
                    clearInterval(this.timer);
                    return;
                }

                this.live = payload.live;
                this.isUnavailable = this.live.unavailable_in_region ?? false;
                this.statusMessage = null;
            } catch (e) {
                // Тихо пропускаем сбой сети до следующего интервала.
            }
        },
    }));
});
</script>
@endpush
@endonce
