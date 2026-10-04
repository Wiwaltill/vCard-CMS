(() => {
    const form = document.getElementById('imageMaintenance');
    if (!form) return;
    const english = document.documentElement.lang === 'en';
    const text = (de, en) => english ? en : de;
    const start = form.querySelector('[type=submit]');
    const pause = document.getElementById('pauseOptimization');
    const status = document.getElementById('optimizationStatus');
    const progress = document.getElementById('optimizationProgress');
    const diagnostics = document.getElementById('optimizationDiagnostics');
    const diagnosticText = document.getElementById('optimizationDiagnosticText');
    const showDetails = value => {
        if (!value) return;
        diagnostics.hidden = false;
        diagnostics.open = true;
        diagnosticText.textContent = value;
    };
    const skip = document.getElementById('skipOptimization');
    let currentJob = form.dataset?.jobId ? {id:form.dataset.jobId, done:Number(form.dataset.jobDone), total:Number(form.dataset.jobTotal)} : null;
    let running = false, paused = false;
    const render = job => {
        currentJob = job;
        skip.disabled = running || job.done >= job.total;
        progress.max = Math.max(1, job.total);
        progress.value = job.done;
        status.textContent = `${job.done} / ${job.total}`;
        document.getElementById('optimizationBackup').textContent = `Backup: ${job.backup}`;
        const rows = job.report.map(entry => {
            const row = document.createElement('li');
            row.className = 'list-group-item';
            row.textContent = `${entry.label}: ${entry.status === 'optimized' ? text('Optimiert', 'Optimized') : text('Übersprungen', 'Skipped')} — ${entry.reason}`;
            return row;
        });
        document.getElementById('optimizationReport').replaceChildren(...rows);
    };
    const request = async (action, id = '', expectedDone = '', prepared = null) => {
        let payload = new URLSearchParams({action, job_id: id, expected_done: String(expectedDone), csrf_token: form.elements.csrf_token.value});
        if (prepared) {
            const multipart = new FormData();
            for (const [key, value] of payload) multipart.append(key, value);
            multipart.append('prepared_image', prepared.blob, `prepared.${prepared.extension}`);
            payload = multipart;
        }
        let response;
        try {
            response = await fetch('/admin/maintenance', {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body: payload});
        } catch (error) {
            showDetails(`${action}: ${error.name}: ${error.message}`);
            throw new Error(text('Verbindung zum Server unterbrochen. Du kannst den Durchlauf fortsetzen.', 'Connection interrupted. You can resume this run.'));
        }
        if (response.redirected || response.status === 401 || response.status === 403) throw new Error(text('Bitte die Seite neu laden und gegebenenfalls erneut anmelden.', 'Please reload the page and sign in again if needed.'));
        let result;
        const body = await response.text();
        const responseDetails = `${action} — HTTP ${response.status}\n${body.slice(0, 6000)}${body.length > 6000 ? '\n…' : ''}`;
        try {result = JSON.parse(body);}
        catch {
            showDetails(responseDetails);
            throw new Error(text('Der Server hat keine gültige Antwort geliefert. Die Fehlerdetails stehen unten. Du kannst den Durchlauf fortsetzen.', 'The server returned an invalid response. See the error details below. You can resume this run.'));
        }
        if (result?.details) showDetails(`${action} — HTTP ${response.status}\n${result.details}`);
        if (!response.ok) showDetails(responseDetails);
        if (!response.ok) throw new Error(result.error || text('Optimierung fehlgeschlagen.', 'Optimization failed.'));
        if (!result || typeof result.id !== 'string' || !Number.isInteger(result.done) || !Number.isInteger(result.total) || !Array.isArray(result.report)) throw new Error(text('Ungültiger Fortschrittsbericht vom Server. Bitte die Seite neu laden.', 'Invalid progress report from the server. Please reload the page.'));
        return result;
    };
    skip.addEventListener('click', async () => {
        if (running || !currentJob) return;
        const previous = currentJob;
        running = true; start.disabled = true; skip.disabled = true;
        try {
            const saved = await request('status', previous.id);
            if (saved.done !== previous.done) {
                render(saved);
                status.textContent += text(' — Fortschritt aktualisiert. Bitte das aktuelle Bild erneut prüfen.', ' — Progress updated. Please check the current image again.');
            } else {
                render(await request('skip', saved.id, saved.done));
                status.textContent += text(' — Bild übersprungen. Du kannst den Durchlauf jetzt fortsetzen.', ' — Image skipped. You can now resume the run.');
            }
        } catch (error) {status.textContent = error.message;}
        finally {running = false; start.disabled = false; skip.disabled = !currentJob || currentJob.done >= currentJob.total;}
    });
    pause.addEventListener('click', () => {paused = true; pause.disabled = true;});
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (running) return;
        diagnostics.hidden = true; diagnosticText.textContent = '';
        running = true; paused = false; start.disabled = true; pause.disabled = false; skip.disabled = true;
        try {
            let job = await request('start'); render(job);
            while (job.done < job.total && !paused) {
                let next;
                try {
                    let prepared = null;
                    if (job.next && window.vcardBrowserImages) {
                        status.textContent = `${job.done} / ${job.total} — ${text('Bild wird im Browser verkleinert …', 'Resizing image in browser …')}`;
                        try {
                            if (!/^\/uploads\/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)$/.test(job.next.path)) throw new Error('Invalid image path');
                            const source = await fetch(job.next.path, {credentials:'same-origin'});
                            if (!source.ok || source.redirected) throw new Error(`HTTP ${source.status}`);
                            const downloaded = await source.blob();
                            const original = new Blob([downloaded], {type:job.next.mime || downloaded.type});
                            prepared = await window.vcardBrowserImages.resize(original, job.next.max_dimension);
                        } catch (error) {showDetails(text('Browser-Verkleinerung: ', 'Browser resizing: ') + error.message);}
                    }
                    next = await request('step', job.id, job.done, prepared);
                }
                catch (error) {
                    // A failed response may follow a successfully committed step.
                    // Read persisted progress before deciding whether to stop.
                    let saved;
                    try {saved = await request('status', job.id);} catch {throw error;}
                    render(saved);
                    if (saved.done <= job.done) throw error;
                    next = saved;
                }
                job = next; render(job);
            }
            status.textContent += job.done === job.total ? text(' — Abgeschlossen.', ' — Completed.') : text(' — Pausiert.', ' — Paused.');
            if (job.report.length) {
                const optimized = job.report.filter(entry => entry.status === 'optimized').length;
                const already = job.report.filter(entry => entry.code === 'already_optimized').length;
                const skipped = job.report.length - optimized - already;
                status.textContent += ` ${optimized} ${text('optimiert', 'optimized')}, ${already} ${text('bereits optimiert', 'already optimized')}, ${skipped} ${text('nicht verarbeitet', 'not processed')}.`;
            }
        } catch (error) {status.textContent = error.message;}
        finally {running = false; start.disabled = false; pause.disabled = true; skip.disabled = !currentJob || currentJob.done >= currentJob.total;}
    });
    window.addEventListener('beforeunload', event => {
        if (running) {event.preventDefault(); event.returnValue = '';}
    });
})();
