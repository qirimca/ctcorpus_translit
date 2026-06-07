#!/usr/bin/env node
/**
 * Parity gate: verifies the client-side JS engine (assets/mod_translit.js)
 * produces byte-for-byte the same output as the PHP engine
 * (translit/TranslitProcessor.php) for both directions.
 *
 * Usage (from mod_translit/):
 *     php translit/export_rules.php > assets/translit-data.js   # if rules changed
 *     node test/parity.js
 *
 * Requires the `php` CLI on PATH. Exits non-zero on any mismatch.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const DATA = path.join(ROOT, 'assets', 'translit-data.js');
const ENGINE = path.join(ROOT, 'assets', 'mod_translit.js');

function loadEngine() {
    const raw = fs.readFileSync(DATA, 'utf8')
        .replace(/^[\s\S]*?window\.ModTranslitData\s*=/, '')
        .replace(/;\s*$/, '');
    const data = JSON.parse(raw);
    const mod = require(ENGINE);
    return mod.create(data);
}

function phpTranslate(lines, variant) {
    // Exchange data as JSON so inputs containing newlines can't collide with a
    // line-based protocol.
    const script =
        '$in=json_decode(stream_get_contents(STDIN),true);' +
        'chdir(' + JSON.stringify(path.join(ROOT, 'translit')) + ');' +
        "require 'TranslitProcessor.php';" +
        '$out=array_map(fn($x)=>TranslitProcessor::translate($x,' +
        JSON.stringify(variant) + '),$in);' +
        'echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);';
    const res = execFileSync('php', ['-r', script], { input: JSON.stringify(lines), maxBuffer: 1 << 28 });
    return JSON.parse(res.toString('utf8'));
}

function buildCorpus() {
    const cyr = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя';
    const lat = 'aâbcçdefgğhıijklmnñoöpqrsştuüvxyz';
    const lines = [];
    let seed = 987654321;
    const rnd = () => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };
    const gen = (alpha) => {
        const n = 1 + Math.floor(rnd() * 9);
        let w = '';
        for (let j = 0; j < n; j++) w += alpha[Math.floor(rnd() * alpha.length)];
        if (rnd() < 0.3) w = w[0].toUpperCase() + w.slice(1);
        if (rnd() < 0.1) w = w.toUpperCase();
        return w;
    };
    for (let i = 0; i < 5000; i++) lines.push(gen(cyr));
    for (let i = 0; i < 5000; i++) lines.push(gen(lat));
    lines.push('Селям, дунья!\nБугунь — яхшы кунь.\nКъач саат?');
    lines.push('ABC-абв/Къырым.\tНомер №5; «тест»');
    lines.push('ёёё ЮЮ ЦЦ ЩЩ', 'Я Ё Е', '   ', '');
    return lines;
}

function main() {
    const engine = loadEngine();
    const corpus = buildCorpus();
    let failures = 0;
    for (const variant of ['crh-latn', 'crh-cyrl']) {
        const php = phpTranslate(corpus, variant);
        const js = corpus.map((l) => engine.translate(l, variant));
        let mism = 0;
        for (let i = 0; i < corpus.length; i++) {
            if (php[i] !== js[i]) {
                if (mism < 10) {
                    console.error(`  [${variant}] IN ${JSON.stringify(corpus[i])} PHP ${JSON.stringify(php[i])} JS ${JSON.stringify(js[i])}`);
                }
                mism++;
            }
        }
        console.log(`${variant}: ${corpus.length} inputs, ${mism} mismatch(es)`);
        failures += mism;
    }
    if (failures > 0) {
        console.error(`PARITY FAILED: ${failures} mismatch(es).`);
        process.exit(1);
    }
    console.log('PARITY OK: JS engine matches PHP byte-for-byte.');
}

main();
