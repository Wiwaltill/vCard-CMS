(() => {
    const text = (de, en) => document.documentElement.lang === 'en' ? en : de;
    async function resize(blob, maxDimension) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(blob.type) || blob.size > 5 * 1024 * 1024) throw new Error(text('Bitte PNG, JPEG oder WebP bis 5 MB verwenden.', 'Use PNG, JPEG or WebP up to 5 MB.'));
        const url = URL.createObjectURL(blob);
        const image = new Image();
        try {
            // Browsers apply EXIF orientation while decoding; canvas exports pixels
            // without carrying the original EXIF data into the prepared upload.
            image.src = url;
            await image.decode();
            if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth > 8192 || image.naturalHeight > 8192 || image.naturalWidth * image.naturalHeight > 25000000) throw new Error(text('Bildmaße überschreiten das erlaubte Limit.', 'Image dimensions exceed the allowed limit.'));
            const scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const context = canvas.getContext('2d');
            if (!context) throw new Error(text('Bildverarbeitung im Browser nicht verfügbar.', 'Browser image processing unavailable.'));
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            try {
                const output = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', 0.9));
                if (!output) throw new Error(text('Bild konnte nicht verkleinert werden.', 'Could not resize image.'));
                return {blob:output, extension:output.type === 'image/webp' ? 'webp' : 'png'};
            } finally {canvas.width = 1; canvas.height = 1;}
        } finally {image.src = ''; URL.revokeObjectURL(url);}
    }
    window.vcardBrowserImages = {resize};
    for (const input of document.querySelectorAll('input[type="file"][name="bild"], input[type="file"][name="company_logo"]')) {
        const form = input.form;
        const status = document.createElement('div');
        status.className = 'form-text'; status.setAttribute('role', 'status');
        input.insertAdjacentElement('afterend', status);
        let pending, revision = 0, internalChange = false;
        input.addEventListener('change', () => {
            if (internalChange) return;
            const version = ++revision, file = input.files[0];
            if (!file) {status.textContent = ''; pending = null; return;}
            status.textContent = text('Bild wird im Browser vorbereitet …', 'Preparing image in browser …');
            const task = (async () => {
                try {
                    const result = await resize(file, input.name === 'company_logo' ? 1200 : 768);
                    if (version !== revision) return;
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([result.blob], `prepared.${result.extension}`, {type:result.blob.type}));
                    input.files = transfer.files;
                    internalChange = true;
                    try {input.dispatchEvent(new Event('change', {bubbles:true}));} finally {internalChange = false;}
                    status.textContent = text('Für den Upload vorbereitet: ', 'Prepared for upload: ') + Math.ceil(result.blob.size / 1024) + ' KB';
                } catch (error) {
                    if (version === revision) status.textContent = text('Browser-Optimierung nicht möglich; das Original wird beim Speichern geprüft. ', 'Browser optimization unavailable; the original will be validated when saved. ') + error.message;
                }
            })();
            pending = task;
            task.finally(() => {if (pending === task) pending = null;});
        });
        form.addEventListener('submit', async event => {
            if (!pending || ['delete_bild', 'delete_company_logo'].includes(event.submitter?.name)) return;
            event.preventDefault();
            const submitter = event.submitter;
            while (pending) await pending;
            form.requestSubmit(submitter || undefined);
        });
    }
})();
