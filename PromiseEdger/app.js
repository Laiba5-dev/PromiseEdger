/* PromiseEdger client script: theme, reactions, check-ins, follow, status, comments, reports, notifications, confetti */
(function () {
    'use strict';
    var PE = window.PE || { strings: {}, csrf: '' };
    var S = PE.strings || {};
    var html = document.documentElement;
    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

    // ---------- toast ----------
    var toastTimer;
    function toast(msg) {
        var t = $('#toast'); if (!t) return;
        t.textContent = msg; t.classList.add('show');
        clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2800);
    }

    // ---------- network ----------
    function post(url, data) {
        var body = data instanceof URLSearchParams ? data : new URLSearchParams(data || {});
        if (!body.has('csrf')) body.set('csrf', PE.csrf);
        return fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }
    function fail(e) { toast((e && e.message && e.message !== 'x') ? S.network_error : S.network_error); }
    function fromHtml(str) { var t = document.createElement('template'); t.innerHTML = str.trim(); return t.content; }
    function swap(el, str) { if (!el) return null; var n = fromHtml(str).firstElementChild; el.replaceWith(n); return n; }

    // ---------- theme ----------
    var tt = $('#themeToggle');
    if (tt) tt.addEventListener('click', function () {
        var next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.classList.add('theme-anim');
        html.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('pe-theme', next); } catch (e) {}
        setTimeout(function () { html.classList.remove('theme-anim'); }, 500);
        window.dispatchEvent(new CustomEvent('pe:theme'));
    });

    // ---------- alerts auto close ----------
    setTimeout(function () {
        $$('.alert-dismissible').forEach(function (a) { if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(a).close(); });
    }, 6000);

    // ---------- scroll reveal + count-up ----------
    (function () {
        var reveals = $$('.reveal'), counters = $$('[data-count]');
        function count(el) {
            var end = parseInt(el.dataset.count, 10) || 0, t0 = null, dur = 1200;
            if (end === 0) { el.textContent = '0'; return; }
            function step(t) {
                if (!t0) t0 = t;
                var p = Math.min((t - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(end * e);
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }
        if (!('IntersectionObserver' in window)) {
            reveals.forEach(function (e) { e.classList.add('in'); });
            counters.forEach(function (e) { e.textContent = e.dataset.count; });
            return;
        }
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
        }, { threshold: .1 });
        reveals.forEach(function (e) { io.observe(e); });
        var co = new IntersectionObserver(function (es) {
            es.forEach(function (en) { if (en.isIntersecting) { co.unobserve(en.target); count(en.target); } });
        }, { threshold: .4 });
        counters.forEach(function (e) { co.observe(e); });
    })();

    // ---------- confirm on forms ----------
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-confirm]');
        if (f && !window.confirm(f.dataset.confirm)) e.preventDefault();
    });

    // ---------- confetti ----------
    function confetti(originEl) {
        var c = $('#confetti'); if (!c || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var ctx = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
        var r = originEl ? originEl.getBoundingClientRect() : { left: innerWidth / 2, top: innerHeight / 2, width: 0, height: 0 };
        var ox = r.left + r.width / 2, oy = Math.min(Math.max(r.top + r.height / 2, 80), innerHeight - 80);
        var colors = ['#C5A253', '#D8CBAF', '#F5F3EF', '#171717', '#E6CF8E'];
        var parts = [];
        for (var i = 0; i < 110; i++) {
            var a = Math.random() * Math.PI * 2, sp = 4 + Math.random() * 9;
            parts.push({ x: ox, y: oy, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - 5, g: .22 + Math.random() * .12, s: 5 + Math.random() * 7,
                rot: Math.random() * 6, vr: (Math.random() - .5) * .4, c: colors[i % colors.length], life: 0 });
        }
        var t0 = performance.now();
        (function frame(t) {
            ctx.clearRect(0, 0, c.width, c.height);
            parts.forEach(function (p) {
                p.vy += p.g; p.vx *= .992; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
                ctx.globalAlpha = Math.max(0, 1 - (t - t0) / 2600);
                ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); ctx.restore();
            });
            if (t - t0 < 2600) requestAnimationFrame(frame); else ctx.clearRect(0, 0, c.width, c.height);
        })(t0);
    }

    // ---------- reactions ----------
    function closePickers(except) { $$('.react-wrap.open').forEach(function (w) { if (w !== except) w.classList.remove('open'); }); }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.react-btn');
        if (btn) { var w = btn.closest('.react-wrap'); closePickers(w); w.classList.toggle('open'); return; }
        var opt = e.target.closest('.react-opt');
        if (opt) {
            var wrap = opt.closest('.react-wrap'); if (opt.disabled) return; opt.disabled = true;
            post('react.php', { promise_id: wrap.dataset.id, type: opt.dataset.type }).then(function (d) {
                if (!d.success) { toast(d.message || S.something_wrong); opt.disabled = false; return; }
                var n = swap(wrap, d.html);
                if (d.mine && n) { var b = $('.react-btn', n); if (b) { b.classList.add('pop'); } }
            }).catch(fail);
            return;
        }
        if (!e.target.closest('.react-wrap')) closePickers();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePickers(); });

    // ---------- daily check-in ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.btn-checkin[data-id]'); if (!b) return;
        b.disabled = true;
        post('checkin.php', { promise_id: b.dataset.id }).then(function (d) {
            if (!d.success) { toast(d.message || S.something_wrong); b.disabled = false; return; }
            var n = swap(b.closest('.js-streak'), d.html);
            toast(S.checked_in);
            if (d.milestone) confetti(n);
        }).catch(function (x) { b.disabled = false; fail(x); });
    });

    // ---------- follow ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.follow-btn[data-user]'); if (!b) return;
        var uid = b.dataset.user; b.classList.add('busy');
        post('follow.php', { user_id: uid }).then(function (d) {
            b.classList.remove('busy');
            if (!d.success) { toast(d.message || S.something_wrong); return; }
            $$('.follow-btn[data-user="' + uid + '"]').forEach(function (x) {
                x.classList.toggle('on', d.following); x.dataset.following = d.following ? 1 : 0;
                var ic = $('i', x); if (ic) ic.className = 'bi ' + (d.following ? 'bi-check2' : 'bi-plus-lg');
                var sp = $('span', x); if (sp) sp.textContent = d.following ? S.following : S.follow;
            });
            toast(d.following ? S.followed : S.unfollowed);
        }).catch(function (x) { b.classList.remove('busy'); fail(x); });
    });

    // ---------- status + progress ----------
    document.addEventListener('input', function (e) {
        var r = e.target.closest('.upd-form input[type=range]'); if (!r) return;
        var f = r.closest('form'); $('output', f).textContent = r.value + '%';
        var v = parseInt(r.value, 10), done = $('input[value=done]', f), prog = $('input[value=in_progress]', f);
        if (v === 100) done.checked = true; else if (done.checked) prog.checked = true;
    });
    document.addEventListener('change', function (e) {
        var rad = e.target.closest('.upd-form input[type=radio]'); if (!rad) return;
        var f = rad.closest('form'), r = $('input[type=range]', f);
        if (rad.value === 'done') { r.value = 100; $('output', f).textContent = '100%'; }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('.upd-form'); if (!f) return;
        e.preventDefault();
        var card = f.closest('.p-card'), btn = $('button[type=submit]', f); btn.disabled = true;
        var st = $('input[name=status]:checked', f);
        post('promise_update.php', { promise_id: f.dataset.id, status: st ? st.value : '', progress: $('input[name=progress]', f).value }).then(function (d) {
            btn.disabled = false;
            if (!d.success) { toast(d.message || S.something_wrong); return; }
            swap($('.js-badges', card), d.badges); swap($('.js-progress', card), d.progress_html); swap($('.js-streak', card), d.streak);
            card.className = card.className.replace(/status-\w+/, 'status-' + d.status);
            toast(S.saved);
            if (d.celebrate) { card.classList.add('celebrate'); confetti(card); setTimeout(function () { card.classList.remove('celebrate'); }, 1200); }
        }).catch(function (x) { btn.disabled = false; fail(x); });
    });

    // ---------- share ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.share-btn'); if (!b) return;
        var url = new URL(b.dataset.url, location.href).href;
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(function () { toast(S.link_copied); }, function () { window.prompt('', url); });
        else window.prompt('', url);
    });

    // ---------- comments (ajax) ----------
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('.cmt-form'); if (!f) return;
        e.preventDefault();
        var inp = $('input[name=comment]', f), btn = $('button', f); if (!inp.value.trim()) return;
        btn.disabled = true;
        post('comment.php', new URLSearchParams(new FormData(f))).then(function (d) {
            btn.disabled = false;
            if (!d.success) { toast(d.message || S.something_wrong); return; }
            var box = f.closest('.comment-box'), list = $('.cmt-list', box), no = $('.js-nocomments', box);
            if (no) no.remove();
            list.appendChild(fromHtml(d.html));
            var cc = f.closest('.p-card').querySelector('.js-ccount'); if (cc) cc.textContent = d.count;
            inp.value = '';
        }).catch(function (x) { btn.disabled = false; fail(x); });
    });

    // ---------- report ----------
    var rm = $('#reportModal'), rf = $('#reportForm');
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.report-btn'); if (!b || !rm) return;
        $('#reportPromiseId').value = b.dataset.id; rf.reset(); $('#reportPromiseId').value = b.dataset.id;
        bootstrap.Modal.getOrCreateInstance(rm).show();
    });
    if (rf) rf.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('button[type=submit]', rf); btn.disabled = true;
        post('report.php', new URLSearchParams(new FormData(rf))).then(function (d) {
            btn.disabled = false; toast(d.message || (d.success ? S.report_sent : S.something_wrong));
            if (d.success || d.message) {
                bootstrap.Modal.getOrCreateInstance(rm).hide();
                var id = $('#reportPromiseId').value;
                if (d.success) $$('.report-btn[data-id="' + id + '"]').forEach(function (x) {
                    x.disabled = true; x.classList.add('reported'); var i = $('i', x); if (i) i.className = 'bi bi-flag-fill';
                });
            }
        }).catch(function (x) { btn.disabled = false; fail(x); });
    });

    // ---------- load more ----------
    var lm = $('#loadMore');
    if (lm) lm.addEventListener('click', function () {
        lm.disabled = true;
        fetch(lm.dataset.url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
            var frag = fromHtml(h), marker = $('.more-marker', frag);
            if (marker) { lm.dataset.url = marker.dataset.url; marker.remove(); } else { $('#loadMoreWrap').classList.add('d-none'); }
            $(lm.dataset.target).appendChild(frag); lm.disabled = false;
        }).catch(function (x) { lm.disabled = false; fail(x); });
    });

    // ---------- notifications ----------
    var bell = $('#bellBtn'), badge = $('#bellCount');
    function setUnread(n) {
        if (!badge) return;
        badge.textContent = n > 99 ? '99+' : n; badge.classList.toggle('d-none', n <= 0);
        bell.classList.toggle('has-unread', n > 0);
    }
    var mar = $('#markAllRead');
    if (mar) mar.addEventListener('click', function () {
        post('notifications.php', { action: 'mark_all' }).then(function (d) {
            if (d.success) { $$('.notif-item.unread').forEach(function (x) { x.classList.remove('unread'); }); setUnread(0); toast(S.mark_all_read_done); }
        }).catch(fail);
    });
    if (PE.logged && bell) {
        var last = badge ? parseInt(badge.textContent, 10) || 0 : 0;
        setInterval(function () {
            if (document.hidden) return;
            fetch('notifications.php?count=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.success) { setUnread(d.unread); last = d.unread; }
            }).catch(function () {});
        }, 30000);
    }
})();
