(() => {
    const forms = [...document.querySelectorAll('form[data-unsaved-warning], form[data-contact-editor]')];
    if (!forms.length) return;
    const snapshot = form => JSON.stringify([...new FormData(form)].map(([key, value]) => [key, typeof value === 'string' ? value : (value.name ? [value.name, value.size, value.lastModified] : '')]));
    const initial = new Map();
    // Let editor initialization populate automatic values before recording them.
    window.addEventListener('load', () => {for (const form of forms) initial.set(form, snapshot(form));});
    let submitting = false;
    for (const form of document.querySelectorAll('form')) form.addEventListener('submit', event => {
        if (forms.includes(form) && !event.defaultPrevented) submitting = true;
    });
    window.addEventListener('beforeunload', event => {
        if (!submitting && forms.some(form => initial.has(form) && initial.get(form) !== snapshot(form))) {
            event.preventDefault(); event.returnValue = '';
        }
    });
    window.addEventListener('pageshow', () => {submitting = false;});
})();
