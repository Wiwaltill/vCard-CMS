import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../public/assets/js/image-maintenance.js', import.meta.url), 'utf8');
let checks = 0;
const job = done => ({id:'job1',done,total:2,backup:'backup.zip',report:Array.from({length:done},()=>({label:'Test',status:'skipped',reason:'Already optimized'}))});
async function scenario(committed, networkError = false) {
    const listeners = {};
    const nodes = new Map();
    const node = id => {
        if (!nodes.has(id)) nodes.set(id,{textContent:'',disabled:false,addEventListener(event, callback) {listeners[`${id}:${event}`]=callback;}, replaceChildren(...children) {this.children=children;}});
        return nodes.get(id);
    };
    const form = node('imageMaintenance');
    form.elements = {csrf_token:{value:'test-token'}};
    form.querySelector = () => node('start');
    const calls = [];
    let firstStep = true;
    vm.runInNewContext(source, {
        document:{documentElement:{lang:'de'},getElementById:node,createElement:()=>({})},
        window:{addEventListener(){}},URLSearchParams,
        fetch:async (url, options) => {
            const action = options.body.get('action'); calls.push(action);
            if (action === 'step' && firstStep) {
                firstStep=false;
                if (networkError) throw new TypeError('Load failed');
                return {ok:true,status:200,redirected:false,text:async()=>'<html>PHP warning</html>'};
            }
            const result= action === 'start' ? job(0) : action === 'status' ? job(committed ? 1 : 0) : action === 'skip' ? job(1) : job(2);
            return {ok:true,status:200,redirected:false,text:async()=>JSON.stringify(result)};
        },
    });
    await listeners['imageMaintenance:submit']({preventDefault(){}});
    assert.equal(node('optimizationDiagnostics').hidden,false);checks++;
    assert.match(node('optimizationDiagnosticText').textContent, networkError ? /Load failed/ : /HTTP 200[\s\S]*<html>PHP warning<\/html>/);checks++;
    assert.equal(node('start').disabled,false);checks++;
    assert.equal(node('pauseOptimization').disabled,true);checks++;
    if (committed) {
        assert.deepEqual(calls,['start','step','status','step']);checks++;
        assert.match(node('optimizationStatus').textContent,/^2 \/ 2 — Abgeschlossen\. 0 optimiert, 0 bereits optimiert, 2 nicht verarbeitet\.$/);checks++;
        assert.equal(node('optimizationProgress').value,2);checks++;
    } else {
        assert.deepEqual(calls,['start','step','status']);checks++;
        assert.match(node('optimizationStatus').textContent, networkError ? /Verbindung zum Server unterbrochen/ : /keine gültige Antwort/);checks++;
        assert.equal(node('optimizationProgress').value,0);checks++;
        assert.equal(node('skipOptimization').disabled,false);checks++;
        await listeners['skipOptimization:click']();
        assert.deepEqual(calls.slice(-2),['status','skip']);checks++;
        assert.equal(node('optimizationProgress').value,1);checks++;
        assert.match(node('optimizationStatus').textContent,/Bild übersprungen/);checks++;

    }
}
await scenario(true);
await scenario(false);
await scenario(false,true);
console.log(`OK: ${checks} migration response checks passed.`);
