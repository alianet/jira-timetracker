/* SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski */
/* SPDX-License-Identifier: MIT */

(() => {
    const localeSelect = document.getElementById('locale-select');
    if (!localeSelect) {
        return;
    }

    localeSelect.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('locale', localeSelect.value);
        window.location.href = url.toString();
    });
})();
