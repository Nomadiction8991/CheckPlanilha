import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('CNPJ mask preserves alphanumeric identifiers and formats uppercase', () => {
    const listeners = {};
    const attributes = {};
    const input = {
        value: '12abc34501de35',
        dataset: { mask: 'cnpj' },
        selectionStart: 14,
        addEventListener(name, callback) {
            listeners[name] = callback;
        },
        hasAttribute(name) {
            return Object.hasOwn(attributes, name);
        },
        setAttribute(name, value) {
            attributes[name] = value;
        },
        setSelectionRange(start, end) {
            this.selectionStart = start;
            this.selectionEnd = end;
        },
    };
    const document = {
        activeElement: input,
        readyState: 'complete',
        querySelectorAll: () => [],
    };
    const window = {};

    vm.runInNewContext(readFileSync('public/assets/forms/input-mask.js', 'utf8'), {
        document,
        HTMLInputElement: class HTMLInputElement {},
        window,
    });

    window.LegacyInputMask.bindInput(input);

    assert.equal(input.value, '12.ABC.345/01DE-35');
    assert.equal(attributes.inputmode, 'text');

    input.value = '12abc34501de35';
    listeners.input();
    assert.equal(input.value, '12.ABC.345/01DE-35');
});
