(function () {
    'use strict';

    /* ============================================================
       Form validation — highlight empty required fields
       ============================================================ */
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var bad = null;

            form.querySelectorAll('[required]').forEach(function (f) {
                var empty = String(f.value || '').trim() === '';
                f.classList.toggle('is-invalid', empty);
                if (empty && !bad) bad = f;
            });

            if (bad) {
                e.preventDefault();
                bad.focus();
            }
        });
    });

    /* ============================================================
       Live search — autocomplete suggestions
       ============================================================ */
    document.querySelectorAll('.live-search').forEach(function (input) {
        var box = input.parentElement.querySelector('.suggestions');
        var timer = null;

        function hide() {
            box.hidden = true;
            box.innerHTML = '';
        }

        function render(items) {
            box.innerHTML = '';

            if (!items.length) {
                hide();
                return;
            }

            items.forEach(function (text) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = text;
                b.addEventListener('click', function () {
                    input.value = text;
                    hide();
                });
                box.appendChild(b);
            });

            box.hidden = false;
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();

            if (q.length < 2) {
                hide();
                return;
            }

            timer = setTimeout(function () {
                fetch(input.dataset.suggestUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(render)
                    .catch(hide);
            }, 180);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') hide();
        });

        document.addEventListener('click', function (e) {
            if (!input.parentElement.contains(e.target)) hide();
        });
    });

    /* ============================================================
       Confirm-before-submit forms
       ============================================================ */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

})();