/**
 * Study Vault protected viewer.
 *  - Renders the PDF (streamed by secure-document.php) with pdf.js onto canvases.
 *  - Bakes a personalised watermark into every rendered page AND shows a tiled
 *    overlay on top of the document.
 *  - Falls back to the browser's built-in PDF viewer if pdf.js cannot be loaded.
 *
 * NOTE: these are deterrents. A determined person can still photograph the
 * screen, so the watermark's job is to make leaks traceable, not impossible.
 */
(function () {
    'use strict';

    var stage = document.getElementById('viewer');
    if (!stage) return;

    var src = stage.dataset.src;
    var lines = [];
    try { lines = JSON.parse(stage.dataset.watermark || '[]'); } catch (e) { lines = []; }

    var pagesEl = document.getElementById('pages');
    var statusEl = document.getElementById('viewerStatus');
    var overlay = document.getElementById('wmOverlay');
    var zoomLabel = document.getElementById('zoomLabel');

    var zoom = 1;
    var pdfDoc = null;
    var observer = null;

    /* ---------- Watermark overlay (DOM tiles) ---------- */
    function buildOverlay() {
        overlay.innerHTML = '';
        var tileW = 340, tileH = 190;
        var cols = Math.ceil(window.innerWidth / tileW) + 2;
        var rows = Math.ceil(window.innerHeight / tileH) + 2;
        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var t = document.createElement('div');
                t.className = 'wm-tile';
                t.style.left = (c * tileW - 40 + (r % 2 ? tileW / 2 : 0)) + 'px';
                t.style.top = (r * tileH - 20) + 'px';
                lines.forEach(function (ln, i) {
                    var s = document.createElement('span');
                    s.textContent = ln;
                    if (i === 0) s.className = 'wm-strong';
                    t.appendChild(s);
                });
                overlay.appendChild(t);
            }
        }
    }

    // Put the overlay back if someone deletes it from the page.
    new MutationObserver(function () {
        if (!document.body.contains(overlay)) {
            document.body.appendChild(overlay);
            buildOverlay();
        }
    }).observe(document.body, { childList: true });

    window.addEventListener('resize', buildOverlay);

    /* ---------- Watermark baked into each canvas ---------- */
    function stampCanvas(canvas) {
        var ctx = canvas.getContext('2d');
        var w = canvas.width, h = canvas.height;
        var size = Math.max(14, Math.round(w / 38));
        var lh = size * 1.35;
        var tileW = size * 20, tileH = lh * (lines.length + 3);

        ctx.save();
        ctx.globalAlpha = 0.11;
        ctx.fillStyle = '#17492f';
        ctx.textAlign = 'center';
        ctx.translate(w / 2, h / 2);
        ctx.rotate(-Math.PI / 6);
        var span = Math.sqrt(w * w + h * h);
        var row = 0;
        for (var y = -span / 2; y < span / 2; y += tileH, row++) {
            for (var x = -span / 2 + (row % 2 ? tileW / 2 : 0); x < span / 2; x += tileW) {
                lines.forEach(function (ln, i) {
                    ctx.font = (i === 0 ? 'bold ' : '') + size + 'px Arial, sans-serif';
                    ctx.fillText(ln, x, y + i * lh);
                });
            }
        }
        ctx.restore();
    }

    /* ---------- pdf.js rendering ---------- */
    function setStatus(msg) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.style.display = msg ? 'block' : 'none';
    }

    function renderPage(holder) {
        if (holder.dataset.done) return;
        holder.dataset.done = '1';
        var pageNum = parseInt(holder.dataset.page, 10);
        pdfDoc.getPage(pageNum).then(function (page) {
            var dpr = window.devicePixelRatio || 1;
            var viewport = page.getViewport({ scale: parseFloat(holder.dataset.scale) * dpr });
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = (canvas.width / dpr) + 'px';
            canvas.style.height = (canvas.height / dpr) + 'px';
            return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise.then(function () {
                stampCanvas(canvas);
                holder.innerHTML = '';
                holder.appendChild(canvas);
            });
        }).catch(function () { holder.textContent = 'This page could not be displayed.'; });
    }

    function build() {
        pagesEl.innerHTML = '';
        if (observer) observer.disconnect();
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) renderPage(en.target); });
        }, { rootMargin: '600px 0px' });

        var avail = Math.min(stage.clientWidth - 32, 1100);
        var jobs = [];
        for (var i = 1; i <= pdfDoc.numPages; i++) {
            jobs.push(pdfDoc.getPage(i));
        }
        Promise.all(jobs).then(function (pages) {
            pages.forEach(function (page, idx) {
                var base = page.getViewport({ scale: 1 });
                var scale = (avail / base.width) * zoom;
                var holder = document.createElement('div');
                holder.className = 'viewer-page';
                holder.dataset.page = String(idx + 1);
                holder.dataset.scale = String(scale);
                holder.style.width = (base.width * scale) + 'px';
                holder.style.height = (base.height * scale) + 'px';
                pagesEl.appendChild(holder);
                observer.observe(holder);
            });
            setStatus('');
        });
    }

    function setZoom(z) {
        zoom = Math.max(0.5, Math.min(2.5, z));
        zoomLabel.textContent = Math.round(zoom * 100) + '%';
        if (pdfDoc) build();
    }

    document.getElementById('zoomIn').addEventListener('click', function () { setZoom(zoom + 0.15); });
    document.getElementById('zoomOut').addEventListener('click', function () { setZoom(zoom - 0.15); });

    /* ---------- Fallback: built-in browser viewer ---------- */
    function fallback() {
        pagesEl.innerHTML = '';
        var f = document.createElement('iframe');
        f.className = 'viewer-iframe';
        f.title = 'Manuscript';
        f.src = src + '#toolbar=0&navpanes=0';
        pagesEl.appendChild(f);
        document.querySelector('.viewer-controls').style.display = 'none';
        setStatus('');
    }

    /* ---------- Deterrents ---------- */
    stage.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && ['p', 's'].indexOf(e.key.toLowerCase()) !== -1) e.preventDefault();
    });

    /* ---------- Start ---------- */
    buildOverlay();

    if (typeof pdfjsLib === 'undefined') {
        fallback();
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    pdfjsLib.getDocument({ url: src, withCredentials: true }).promise.then(function (doc) {
        pdfDoc = doc;
        build();
    }).catch(function () {
        fallback();
    });
})();
