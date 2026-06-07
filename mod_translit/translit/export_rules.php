<?php
/**
 * Build-time exporter: serialises the PHP transliteration rule data
 * (constants.inc, exeptions.inc, regular_expressions.inc) into a JavaScript
 * data file so the browser can run the *same* engine client-side.
 *
 * Run from this directory:
 *     php export_rules.php > ../assets/translit-data.js
 *
 * The emitted file defines `window.ModTranslitData`. It is consumed by
 * assets/mod_translit.js (the JS port of TranslitProcessor). Both the PHP
 * engine and the JS engine therefore read from a single source of truth.
 */

chdir(__DIR__);
require 'constants.inc';
include 'exeptions.inc';            // $mCyrl2LatnEx, $mLatn2CyrlEx
include 'regular_expressions.inc';  // $mCyrl2Latn, $mLatn2Cyrl, $all_other_letters_*

/** Convert an associative PHP array into an ordered array of [key, value] pairs. */
function pairs(array $arr): array
{
    $out = array();
    foreach ($arr as $k => $v) {
        $out[] = array((string) $k, $v);
    }
    return $out;
}

/**
 * Serialise the ordered regex rule list. The special key 'all_other_letters'
 * (whose PHP value is TRUE) marks where the per-character fallback map runs;
 * we emit it as ["all_other_letters", null] so the JS engine can detect it.
 */
function ruleList(array $arr): array
{
    $out = array();
    foreach ($arr as $pat => $rep) {
        if ($pat === 'all_other_letters') {
            $out[] = array('all_other_letters', null);
        } else {
            $out[] = array((string) $pat, (string) $rep);
        }
    }
    return $out;
}

$data = array(
    // Source-script letter class used to tokenise words for each direction.
    // crh-latn converts FROM Cyrillic; crh-cyrl converts FROM Latin.
    'lettersCyrl'    => CRH_C_UC . CRH_C_LC,
    'lettersLatn'    => CRH_L_UC . CRH_L_LC,
    // Whole-word exception maps applied (as literal str_replace) before rules.
    'cyrl2latnEx'    => pairs($mCyrl2LatnEx),
    'latn2cyrlEx'    => pairs($mLatn2CyrlEx),
    // Ordered regular-expression rule lists.
    'mCyrl2Latn'     => ruleList($mCyrl2Latn),
    'mLatn2Cyrl'     => ruleList($mLatn2Cyrl),
    // Per-character fallback maps (strtr) for the all_other_letters step.
    'allOtherCyr2Lat' => pairs($all_other_letters_cyr2lat),
    'allOtherLat2Cyr' => pairs($all_other_letters_lat2cyr),
);

$json = json_encode(
    $data,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

echo "/*\n";
echo " * AUTO-GENERATED FILE - DO NOT EDIT BY HAND.\n";
echo " * Regenerate with:  php translit/export_rules.php > assets/translit-data.js\n";
echo " * Source of truth:  translit/constants.inc, exeptions.inc, regular_expressions.inc\n";
echo " */\n";
echo "window.ModTranslitData = $json;\n";
