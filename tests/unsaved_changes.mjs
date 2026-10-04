import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const events = new Map();
const editor = {values: [['vorname', 'Erika'], ['bild', null]], listeners: {}, addEventListener(name, listener) {this.listeners[name] = listener;}};
const logout = {listeners: {}, addEventListener(name, listener) {this.listeners[name] = listener;}};
let clock = 0;
vm.runInNewContext(fs.readFileSync(new URL('../public/assets/js/unsaved-changes.js', import.meta.url), 'utf8'), {
    document: {querySelectorAll(selector) {return selector === 'form' ? [editor, logout] : [editor];}},
    window: {addEventListener(name, listener) {events.set(name, listener);}},
    FormData: class {constructor(form) {this.form = form;} *[Symbol.iterator]() {for (const [key, value] of this.form.values) yield [key, value ?? {name:'', size:0, lastModified:++clock}];}},
});
let checks = 0;
function warns(expected, message) {
    let prevented = false;
    events.get('beforeunload')({preventDefault() {prevented = true;}});
    assert.equal(prevented, expected, message); checks++;
}
events.get('load')();
warns(false, 'An empty file input must not mark the form dirty');
warns(false, 'Repeated snapshots must remain clean');
editor.values[0][1] = 'Anna';
warns(true, 'Edited text must warn');
logout.listeners.submit({defaultPrevented:false});
warns(true, 'Logout must not silently discard edits');
editor.values[0][1] = 'Erika';
warns(false, 'Reverted text must be clean');
editor.values[1][1] = {name:'photo.jpg',size:2200000,lastModified:123};
warns(true, 'Selecting a photo must warn');
editor.listeners.submit({defaultPrevented:true});
warns(true, 'Cancelled submissions must still warn');
editor.listeners.submit({defaultPrevented:false});
warns(false, 'Saving must not warn');
events.get('pageshow')();
warns(true, 'Returning to the page must restore warnings');
console.log(`OK: ${checks} unsaved change checks passed.`);
