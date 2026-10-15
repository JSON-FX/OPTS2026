// Run: node scripts/check-php-amount.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const ts = require('typescript');
const source = fs.readFileSync('resources/js/Components/Forms/PhpAmountInput.tsx', 'utf8')
    .split('type Props =')[0].replace(/^import.*$/gm, '');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
const api = {};
new Function('exports', compiled)(api);
assert.equal(api.formatPhpAmount('20000'), '20,000');
assert.equal(api.formatPhpAmount('123456789012.34'), '123,456,789,012.34');
assert.equal(api.formatPhpAmount('0.05'), '0.05');
assert.equal(api.formatPhpAmount('20000.'), '20,000.');
assert.equal(api.formatPhpAmount(''), '');
assert.equal(api.parsePhpAmount('₱ 20,000.50'), '20000.50');
assert.equal(api.parsePhpAmount('00012.30'), '12.30');
assert.equal(api.parsePhpAmount('0.05'), '0.05');
assert.equal(api.parsePhpAmount('12.345'), null);
assert.equal(api.parsePhpAmount('-200'), null);
assert.equal(api.parsePhpAmount('1e3'), null);
assert.equal(api.parsePhpAmount('12.3.4'), null);
console.log('PHP amount formatting: 12 checks passed.');
