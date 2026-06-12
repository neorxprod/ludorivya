(() => {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const platformChoices = form.querySelectorAll('input[name="platform_ids[]"]');
            const hasPlatformRule = platformChoices.length > 0;
            const hasCheckedPlatform = Array.from(platformChoices).some((input) => input.checked);

            if (!form.checkValidity() || (hasPlatformRule && !hasCheckedPlatform)) {
                event.preventDefault();
                event.stopPropagation();

                if (hasPlatformRule && !hasCheckedPlatform) {
                    platformChoices[0].setCustomValidity('Choisis au moins une plateforme.');
                }
            } else if (hasPlatformRule) {
                platformChoices.forEach((input) => input.setCustomValidity(''));
            }

            form.classList.add('was-validated');
        });
    });

    document.querySelectorAll('input[name="platform_ids[]"]').forEach((input) => {
        input.addEventListener('change', () => {
            document
                .querySelectorAll('input[name="platform_ids[]"]')
                .forEach((choice) => choice.setCustomValidity(''));
        });
    });

    const filterForm = document.querySelector('[data-filter-form]');
    if (filterForm) {
        filterForm.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => filterForm.requestSubmit());
        });
    }
})();

