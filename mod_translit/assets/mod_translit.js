/**
 * mod_translit - client-side transliteration engine.
 *
 * This is a faithful JavaScript port of the PHP `TranslitProcessor`
 * (translit/TranslitProcessor.php). It consumes the rule data emitted by
 * translit/export_rules.php (window.ModTranslitData) so that the browser
 * produces byte-for-byte the same output as the server engine.
 *
 * Algorithm (mirrors TranslitProcessor::translate):
 *   1. Apply whole-word exception map as literal substring replacements.
 *   2. Tokenise the text into source-script letter runs ("delims") and the
 *      gaps between them ("words"); only letter runs are transliterated.
 *   3. Join letter runs with "\n", apply the ordered regex rules (multiline),
 *      then a per-character fallback map (strtr) at the all_other_letters step.
 *   4. Re-interleave gaps and converted runs in their original order.
 *
 * PCRE -> JS notes:
 *   - PHP applies each rule as `preg_replace("@$pat@um", ...)`: Unicode (u) +
 *     multiline (m), replacing all matches. In JS we use flags "gm" (global +
 *     multiline). All characters involved are in the BMP, so the "u" flag is
 *     not required for correct matching and is omitted to avoid JS
 *     identity-escape errors on patterns that are valid under PCRE.
 *   - `$1`, `$2` backreferences in replacements work identically in JS.
 *   - strtr() with single-character keys is a single left-to-right pass with
 *     no re-substitution; we replicate it with a per-character lookup.
 */
(function (global) {
    'use strict';

    function ModTranslitEngine(data) {
        this.data = data;
        this._compiled = { 'crh-latn': null, 'crh-cyrl': null };
    }

    // Number of capturing groups in a compiled pattern.
    function groupCount(re) {
        return new RegExp(re.source + '|').exec('').length - 1;
    }

    // Emulate PHP preg_replace backreference handling: a `$N`/`${N}` that
    // refers to a group the pattern does NOT define is replaced with the empty
    // string by PCRE, whereas JS would keep it literal. We strip such
    // out-of-range references at compile time so JS output matches PHP.
    function normalizeReplacement(rep, nGroups) {
        return String(rep).replace(/\$(\d+)|\$\{(\d+)\}/g, function (whole, d1, d2) {
            var n = parseInt(d1 !== undefined ? d1 : d2, 10);
            return n <= nGroups ? whole : '';
        });
    }

    // Lazily compile and cache the regex list for a direction.
    ModTranslitEngine.prototype._rules = function (toVariant) {
        if (this._compiled[toVariant]) {
            return this._compiled[toVariant];
        }
        var raw = toVariant === 'crh-latn' ? this.data.mCyrl2Latn : this.data.mLatn2Cyrl;
        var compiled = [];
        for (var i = 0; i < raw.length; i++) {
            var pat = raw[i][0];
            var rep = raw[i][1];
            if (pat === 'all_other_letters') {
                compiled.push({ allOther: true });
            } else {
                var re = new RegExp(pat, 'gm');
                compiled.push({ re: re, rep: normalizeReplacement(rep, groupCount(re)) });
            }
        }
        this._compiled[toVariant] = compiled;
        return compiled;
    };

    // Build a single-pass character map (object) from an array of [char, rep].
    function charMap(pairs) {
        var map = Object.create(null);
        for (var i = 0; i < pairs.length; i++) {
            map[pairs[i][0]] = pairs[i][1];
        }
        return map;
    }

    // strtr() with single-character keys: one left-to-right pass, no re-scan.
    function strtr(text, map) {
        var out = '';
        for (var i = 0; i < text.length; i++) {
            var ch = text[i];
            out += (ch in map) ? map[ch] : ch;
        }
        return out;
    }

    // Literal (non-regex) global replace, matching PHP str_replace semantics.
    function literalReplaceAll(text, search, replace) {
        if (search === '') {
            return text;
        }
        return text.split(search).join(replace);
    }

    // Apply the ordered regex rules + all_other_letters fallback to a string.
    ModTranslitEngine.prototype._regsConvert = function (joined, toVariant) {
        var rules = this._rules(toVariant);
        var otherMap = toVariant === 'crh-latn'
            ? charMap(this.data.allOtherCyr2Lat)
            : charMap(this.data.allOtherLat2Cyr);
        var text = joined;
        for (var i = 0; i < rules.length; i++) {
            if (rules[i].allOther) {
                text = strtr(text, otherMap);
            } else {
                text = text.replace(rules[i].re, rules[i].rep);
            }
        }
        return text;
    };

    /**
     * Transliterate `text` to `toVariant` ('crh-latn' or 'crh-cyrl').
     * Any other variant returns the input unchanged (matches PHP default).
     */
    ModTranslitEngine.prototype.translate = function (text, toVariant) {
        var letters, exceptions;
        if (toVariant === 'crh-latn') {
            letters = this.data.lettersCyrl;       // converting FROM Cyrillic
            exceptions = this.data.cyrl2latnEx;
        } else if (toVariant === 'crh-cyrl') {
            letters = this.data.lettersLatn;        // converting FROM Latin
            exceptions = this.data.latn2cyrlEx;
        } else {
            return text;
        }

        // 1. Whole-word exceptions (literal, in order).
        for (var e = 0; e < exceptions.length; e++) {
            text = literalReplaceAll(text, exceptions[e][0], exceptions[e][1]);
        }

        // 2. Tokenise into letter runs (transliterated) and gaps (preserved).
        //    Equivalent to preg_split('/([letters]+)/u', ..., DELIM_CAPTURE):
        //    even segments = gaps ("words"), odd segments = letter runs ("delims").
        var runRe = new RegExp('[' + letters + ']+', 'g');
        var gaps = [];
        var runs = [];
        var lastIndex = 0;
        var m;
        while ((m = runRe.exec(text)) !== null) {
            gaps.push(text.slice(lastIndex, m.index));
            runs.push(m[0]);
            lastIndex = m.index + m[0].length;
            if (m[0].length === 0) { runRe.lastIndex++; } // guard (defensive)
        }
        gaps.push(text.slice(lastIndex));

        // 3. Convert the letter runs as one "\n"-joined block, then split back.
        if (runs.length > 0) {
            var converted = this._regsConvert(runs.join('\n'), toVariant).split('\n');
            // Length should be preserved; fall back to originals if not.
            if (converted.length === runs.length) {
                runs = converted;
            }
        }

        // 4. Re-interleave: gap0 + run0 + gap1 + run1 + ... + lastGap.
        var out = '';
        for (var i = 0; i < gaps.length; i++) {
            out += gaps[i];
            if (i < runs.length) {
                out += runs[i];
            }
        }
        return out;
    };

    // Factory helper.
    function create(data) {
        return new ModTranslitEngine(data);
    }

    var api = { Engine: ModTranslitEngine, create: create };
    if (global.ModTranslitData) {
        api.instance = new ModTranslitEngine(global.ModTranslitData);
    }
    global.ModTranslit = api;

    // CommonJS export for the Node-based parity test harness.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
