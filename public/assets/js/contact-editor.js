(() => {
    const form = document.querySelector('[data-contact-editor]');
    if (!form) return;
    const first = form.elements.namedItem('vorname');
    const last = form.elements.namedItem('nachname');
    const email = form.elements.namedItem('email');
    const override = form.elements.namedItem('email_override');
    const photo = form.elements.namedItem('bild');
    const frame = document.getElementById('contactPreview');
    const status = document.getElementById('previewStatus');
    let timer, controller, pictureUrl;
    let revision = 0;
    const normalize = value => value.trim().toLowerCase().replaceAll('ä', 'ae').replaceAll('ö', 'oe')
        .replaceAll('ü', 'ue').replaceAll('ß', 'ss').replace(/[^a-z0-9]+/g, '');
    function automaticEmail() {
        const f = normalize(first.value), l = normalize(last.value);
        if (!f && !l) return '';
        const patterns = {vorname: f, nachname: l, initialen: f.slice(0, 1) + l.slice(0, 1),
            'v.nachname': f.slice(0, 1) + '.' + l, vorname_nachname: f + '_' + l,
            vornamenachname: f + l, 'vorname.nachname': f + '.' + l};
        return (patterns[form.dataset.emailPattern] ?? patterns['vorname.nachname']).replace(/^\.+|\.+$/g, '')
            + '@' + form.dataset.emailDomain.replace(/^@/, '').trim().toLowerCase();
    }
    function updateEmail() {
        email.disabled = !override.checked;
        if (!override.checked) email.value = automaticEmail();
        const hint = document.getElementById('automaticEmailHint');
        if (hint) hint.textContent = automaticEmail();
    }
    function applyPicture() {
        const doc = frame.contentDocument;
        if (!doc) return;
        const mode = doc.documentElement.dataset.bsThemeMode;
        if (mode === 'auto') doc.documentElement.dataset.bsTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        if (!pictureUrl) return;
        const wrap = doc.querySelector('.photo-wrap');
        if (!wrap) return;
        const img = doc.createElement('img');
        img.className = 'employee-photo';
        img.alt = first.value + ' ' + last.value;
        img.src = pictureUrl;
        wrap.replaceChildren(img);
    }
    frame.addEventListener('load', applyPicture);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyPicture);
    async function preview(version) {
        controller = new AbortController();
        const params = new URLSearchParams();
        for (const [key, value] of new FormData(form)) if (typeof value === 'string') params.append(key, value);
        status.textContent = form.dataset.previewLoading;
        try {
            const response = await fetch('/admin/preview', {method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: params, signal: controller.signal});
            if (!response.ok || response.redirected) throw new Error('Preview unavailable');
            const html = await response.text();
            if (version !== revision) return;
            frame.srcdoc = html;
            status.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && version === revision) status.textContent = form.dataset.previewError;
        }
    }
    function schedule() {
        updateEmail();
        revision++;
        clearTimeout(timer);
        controller?.abort();
        const version = revision;
        timer = setTimeout(() => preview(version), 300);
    }
    photo.addEventListener('change', () => {
        if (pictureUrl) URL.revokeObjectURL(pictureUrl);
        pictureUrl = photo.files[0] ? URL.createObjectURL(photo.files[0]) : undefined;
        schedule();
    });
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('submit', () => {clearTimeout(timer); controller?.abort(); revision++;});
    window.addEventListener('pagehide', () => {if (pictureUrl) URL.revokeObjectURL(pictureUrl); controller?.abort();});
    schedule();
})();
