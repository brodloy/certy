/* ============================================================================
   APP.JS — deliberately tiny.
   ----------------------------------------------------------------------------
   This boilerplate renders real HTML on the server, so JavaScript is only for
   light enhancement, not for running the app. Right now it does one thing: ask
   for confirmation before any form that deletes something is submitted.

   Mark such a form with  data-confirm="Your message?".
   ========================================================================== */
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    }
});

/* ----------------------------------------------------------------------------
   Mobile nav drawer. On phones the dashboard sidebar is hidden off-screen; the
   hamburger in the top bar slides it in, and tapping the backdrop or any link
   closes it again. Pure class toggling — the slide itself is CSS.
   -------------------------------------------------------------------------- */
(function () {
    var shell = document.querySelector('.app-shell');
    if (!shell) {
        return; // not on an app-layout page
    }

    function setOpen(open) {
        shell.classList.toggle('nav-open', open);
        var toggle = document.querySelector('[data-nav-open]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-nav-open]')) {
            setOpen(!shell.classList.contains('nav-open'));
        } else if (event.target.closest('[data-nav-close]')) {
            setOpen(false);
        } else if (event.target.closest('.app-sidebar a')) {
            setOpen(false); // close after tapping a nav link
        }
    });
})();

/* ----------------------------------------------------------------------------
   "Check now" — on-demand checks without a full page reload. A button marked
   data-check="<id>" checks one target; data-check-all checks them all. We POST
   to /targets/check (CSRF token from the <meta> tag), then patch the row(s)
   in place from the JSON the endpoint returns. No framework — fetch + DOM.
   -------------------------------------------------------------------------- */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        return; // not on an authed app page
    }
    var token = meta.getAttribute('content');
    var checkMeta = document.querySelector('meta[name="check-url"]');
    var checkUrl = checkMeta ? checkMeta.getAttribute('content') : '/targets/check';

    var STATUS_LABEL = { healthy: 'healthy', warning: 'warning', critical: 'critical', expired: 'expired', failed: 'failed', unknown: 'unknown' };
    var STATUS_VAR   = { healthy: 'ok', warning: 'warn', critical: 'danger', expired: 'danger', failed: 'danger', unknown: 'neutral' };

    // Sign-aware "days left" text, mirrors days_left_label() in helpers.php.
    function daysLeftText(d) {
        if (d < 0) { var n = Math.abs(d); return 'expired ' + n + ' day' + (n === 1 ? '' : 's') + ' ago'; }
        return d + ' day' + (d === 1 ? '' : 's') + ' left';
    }

    function setBusy(btn, busy) {
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('is-busy', busy);
        // Icon buttons show busy via a CSS spin (keep their glyph); text buttons
        // swap their label to "Checking…" and back.
        if (btn.hasAttribute('data-icon')) return;
        if (busy) { btn.dataset.label = btn.innerHTML; btn.textContent = 'Checking…'; }
        else if (btn.dataset.label) { btn.innerHTML = btn.dataset.label; }
    }

    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function fmtDate(iso) {
        // iso like "2026-08-24 12:00:00" or "2026-08-24" -> "Aug 24, 2026"
        if (!iso) return '—';
        var d = iso.substring(0, 10).split('-');
        if (d.length !== 3) return iso;
        return MONTHS[parseInt(d[1], 10) - 1] + ' ' + parseInt(d[2], 10) + ', ' + d[0];
    }

    function patchRow(res) {
        var row = document.querySelector('[data-row="' + res.id + '"]');
        if (!row) return;
        var days = row.querySelector('[data-cell="days"]');
        var exp  = row.querySelector('[data-cell="expires"]');
        var chk  = row.querySelector('[data-cell="checked"]');
        var st   = row.querySelector('[data-cell="status"]');
        var cssVar = 'var(--' + (STATUS_VAR[res.status] || 'neutral') + ')';
        if (days) {
            days.style.color = cssVar;
            days.innerHTML = (res.days_left === null || res.days_left === undefined)
                ? '<span class="text-faint">' + (res.status === 'failed' ? 'check failed' : 'not checked') + '</span>'
                : daysLeftText(res.days_left);
        }
        if (exp)  { exp.textContent = res.expires_at ? fmtDate(res.expires_at) : '—'; }
        if (chk)  { chk.textContent = 'just now'; }
        if (st)   { st.innerHTML = '<span class="badge-soft is-' + res.status + '">' + (STATUS_LABEL[res.status] || 'unknown') + '</span>'; }
    }

    // Recompute the KPI cards from the statuses currently shown in the table,
    // so the counts always match the rows after a scan (no page reload needed).
    function recomputeKpis() {
        var counts = { healthy: 0, warning: 0, critical: 0, expired: 0, failed: 0, unknown: 0 };
        document.querySelectorAll('[data-cell="status"] .badge-soft').forEach(function (b) {
            ['healthy', 'warning', 'critical', 'expired', 'failed', 'unknown'].forEach(function (s) {
                if (b.classList.contains('is-' + s)) counts[s]++;
            });
        });
        Object.keys(counts).forEach(function (s) {
            var el = document.querySelector('[data-kpi="' + s + '"]');
            if (el) el.textContent = counts[s];
        });
    }

    function runCheck(id, btn) {
        setBusy(btn, true);
        var body = '_csrf=' + encodeURIComponent(token) + (id ? ('&id=' + encodeURIComponent(id)) : '');
        return fetch(checkUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.ok && data.results) {
                data.results.forEach(patchRow);
                recomputeKpis();
            }
        })
        .catch(function () { /* leave the row as-is on error */ })
        .finally(function () { setBusy(btn, false); });
    }

    document.addEventListener('click', function (event) {
        var one = event.target.closest('[data-check]');
        var all = event.target.closest('[data-check-all]');
        if (one) { event.preventDefault(); runCheck(one.getAttribute('data-check'), one); }
        else if (all) {
            event.preventDefault();
            var done = runCheck(null, all);
            if (all.hasAttribute('data-reload-after')) {
                done.then(function () { window.location.reload(); });
            }
        }
    });
})();

/* ----------------------------------------------------------------------------
   Add-target form: some fields (Port, Strict validation) only apply to SSL
   checks, so hide them when the user picks a Domain check. Progressive — the
   fields are visible by default and the server still handles the values, so
   this is purely a clarity touch. Any element marked [data-ssl-field] toggles.
   -------------------------------------------------------------------------- */
(function () {
    var sel = document.querySelector('[data-type-select]');
    var sslFields = document.querySelectorAll('[data-ssl-field]');
    if (!sel || !sslFields.length) return;
    function sync() {
        var hide = (sel.value === 'domain');
        sslFields.forEach(function (el) { el.style.display = hide ? 'none' : ''; });
    }
    sel.addEventListener('change', sync);
    sync();
})();

/* ----------------------------------------------------------------------------
   Dark-mode toggle. Persists the choice in a first-party cookie that the server
   reads to set data-bs-theme on <html> (see views/layout/app.php), so it sticks
   across page changes and refreshes. The OS preference is the pre-paint default
   until the user makes an explicit choice.
   -------------------------------------------------------------------------- */
(function () {
    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-theme-toggle]')) return;
        var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        document.cookie = 'certy-theme=' + next + ';path=/;max-age=31536000;samesite=lax';
    });
})();
