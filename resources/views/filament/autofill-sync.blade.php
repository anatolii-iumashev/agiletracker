{{-- Chrome/Safari password managers fill inputs without firing input events,
     so Livewire's `wire:model.fill` state stays empty. Before the form is
     submitted, push the DOM values into Livewire so autofilled credentials
     actually authenticate. --}}
<script>
    (() => {
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (! (form instanceof HTMLFormElement)) {
                return;
            }

            form.querySelectorAll('input, textarea').forEach((element) => {
                if (! (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
                    return;
                }

                if (['checkbox', 'radio', 'file'].includes(element.type) || element.value === '') {
                    return;
                }

                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }, true);
    })();
</script>
