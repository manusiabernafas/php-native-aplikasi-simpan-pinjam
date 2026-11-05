document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (ev) { if (!confirm(el.dataset.confirm)) ev.preventDefault(); });
});
