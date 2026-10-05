<?php
/**
 * The footer panorama: the country coast to coast as one engraved skyline, west to east. Decoration only: nothing in
 * it is named or linked. The sky follows the visitor's clock and the hiker walks in when the footer comes into view,
 * stopping near the part of the country the page is about (main.js).
 */
$here = '';
if ( is_singular( 'rp_park' ) ) {
	$here = strtoupper( (string) get_post_meta( get_the_ID(), 'rp_state', true ) );
} elseif ( is_tax( 'rp_place' ) ) {
	$chain = rp_place_chain( get_queried_object() );
	$here  = strtoupper( (string) ( $chain['state'] ?? '' ) );
}
// Roughly where each part of the country sits along the skyline, for the hiker's resting place.
$spots = [ 'CA' => 430, 'OR' => 300, 'WA' => 300, 'NV' => 520, 'AZ' => 695, 'NM' => 760, 'UT' => 868, 'CO' => 900, 'WY' => 957, 'MT' => 980, 'ID' => 940, 'MO' => 1195, 'IL' => 1210, 'TN' => 1290, 'NC' => 1300, 'GA' => 1320, 'FL' => 1362, 'NY' => 1400, 'NJ' => 1400, 'ME' => 1426, 'TX' => 800 ];
$home  = $spots[ $here ] ?? 720;
?>
<div class="pano-wrap" id="pano" data-home="<?php echo (int) $home; ?>" aria-hidden="true">
  <div class="pano">
<svg viewBox="0 0 1440 400" focusable="false">
<defs>
<pattern id="rp-h" width="6" height="5" patternUnits="userSpaceOnUse"><path d="M0 2.5H6" stroke="#0A3F78" stroke-width="0.9"/></pattern>
<pattern id="rp-v" width="4" height="6" patternUnits="userSpaceOnUse"><path d="M2 0V6" stroke="#0A3F78" stroke-width="0.9"/></pattern>
<pattern id="rp-d" width="5" height="5" patternUnits="userSpaceOnUse"><path d="M0 5L5 0" stroke="#0A3F78" stroke-width="0.8"/></pattern>
<pattern id="rp-sky" width="8" height="7" patternUnits="userSpaceOnUse"><path d="M0 3.5H8" stroke="#F3ECD8" stroke-width="0.6"/></pattern>
</defs>

<circle class="sun sun-day" cx="1212" cy="68" r="32" fill="#F3ECD8"/>
<circle class="sun sun-dawn" cx="1284" cy="206" r="38" fill="#F6C89A"/>
<circle class="sun sun-dusk" cx="253" cy="197" r="39" fill="#F2854A"/>
<circle class="sun sun-night" cx="1230" cy="64" r="20" fill="#E9E4D0"/>
<g class="stars" fill="#F3ECD8"><circle cx="97" cy="35" r="1.5"/><circle cx="239" cy="83" r="1"/><circle cx="403" cy="27" r="1.5"/><circle cx="567" cy="65" r="1"/><circle cx="649" cy="23" r="1"/><circle cx="793" cy="49" r="1.5"/><circle cx="1005" cy="31" r="1"/><circle cx="1119" cy="93" r="1.5"/><circle cx="1323" cy="29" r="1"/><circle cx="1387" cy="111" r="1"/></g>

<rect x="0" y="170" width="1440" height="130" fill="url(#rp-sky)" opacity="0.16"/>
<path d="M0 250 Q60 215 120 240 T260 228 T420 246 T600 232 T780 244 T960 226 T1100 240 Q1180 205 1240 222 Q1290 196 1340 220 Q1390 205 1440 232 L1440 320 L0 320 Z" fill="#F3ECD8" opacity="0.3"/>

<g class="lm"><g class="art">
<path d="M14 276H236 M14 284H236 M14 292H236" stroke="#F3ECD8" stroke-width="1" stroke-dasharray="14 6" opacity="0.55" fill="none"/>
<path d="M14 262h222v5H14z M66 172h10v128H66z M176 172h10v128h-10z M62 196h18v4H62z M62 226h18v4H62z M172 196h18v4h-18z M172 226h18v4h-18z" fill="#F3ECD8"/>
<path d="M14 258 L71 176 Q126 268 181 176 L236 258" stroke="#F3ECD8" stroke-width="2.2" fill="none"/>
<path d="M30 235V262 M44 215V262 M58 195V262 M86 198V262 M100 212V262 M114 220V262 M126 222V262 M138 220V262 M152 212V262 M166 198V262 M194 195V262 M208 216V262 M222 237V262" stroke="#F3ECD8" stroke-width="1" fill="none"/>
</g></g>

<g class="lm"><g class="art">
<path d="M262 300 L266 190 L276 190 L280 300 Z M288 300 L293 150 L305 150 L310 300 Z M318 300 L321 204 L329 204 L332 300 Z" fill="#F3ECD8"/>
<path d="M268 296V200 M273 296V200 M295 296V160 M299 296V160 M303 296V160 M323 296V212 M327 296V212" stroke="#0A3F78" stroke-width="0.8" fill="none"/>
<g fill="#F3ECD8"><ellipse cx="271" cy="176" rx="20" ry="26"/><ellipse cx="271" cy="150" rx="14" ry="18"/><ellipse cx="299" cy="132" rx="24" ry="30"/><ellipse cx="299" cy="102" rx="16" ry="20"/><ellipse cx="325" cy="190" rx="16" ry="22"/><ellipse cx="325" cy="168" rx="11" ry="14"/></g>
<g fill="url(#rp-d)" opacity="0.55"><ellipse cx="271" cy="176" rx="20" ry="26"/><ellipse cx="299" cy="132" rx="24" ry="30"/><ellipse cx="325" cy="190" rx="16" ry="22"/></g>
</g></g>

<g class="lm"><g class="art">
<path d="M350 300 L366 262 Q392 168 452 140 Q486 128 498 150 Q504 170 502 300 Z" fill="#F3ECD8"/>
<path d="M350 300 L366 262 Q392 168 452 140 L470 136 L474 300 Z" fill="url(#rp-h)" opacity="0.3"/>
<path d="M470 136 Q486 128 498 150 Q504 170 502 300 L474 300 Z" fill="url(#rp-v)" opacity="0.8"/>
</g></g>

<g class="lm"><g class="art">
<path d="M543 300 V236 Q543 228 549 228 Q555 228 555 236 V300 Z M543 270 H536 Q530 270 530 262 V250 Q530 246 533.5 246 Q537 246 537 250 V262 H543 Z M555 262 H562 Q568 262 568 254 V244 Q568 240 564.5 240 Q561 240 561 244 V255 H555 Z" fill="#F3ECD8"/>
<path d="M547 298V234 M551 298V234" stroke="#0A3F78" stroke-width="0.7" fill="none"/>
</g></g>

<g class="lm"><g class="art">
<path d="M582 300 L590 268 L622 268 L628 240 L664 240 L670 212 L716 212 L722 238 L758 238 L764 266 L800 266 L808 300 Z" fill="#F3ECD8"/>
<path d="M582 300 L590 268 L622 268 L628 240 L664 240 L670 212 L716 212 L722 238 L758 238 L764 266 L800 266 L808 300 Z" fill="url(#rp-h)" opacity="0.4"/>
<path d="M592 280H798 M627 254H760 M669 226H720 M586 292H804" stroke="#0A3F78" stroke-width="1" fill="none"/>
</g></g>

<g class="lm"><g class="art">
<path d="M832 300 L844 236 Q852 196 872 196 Q892 196 896 232 L904 300 L886 300 L880 246 Q876 222 868 222 Q858 222 856 246 L850 300 Z" fill="#F3ECD8"/>
<path d="M832 300 L844 236 Q852 196 872 196 Q892 196 896 232 L904 300 L886 300 L880 246 Q876 222 868 222 Q858 222 856 246 L850 300 Z" fill="url(#rp-d)" opacity="0.5"/>
</g></g>

<g class="lm"><g class="art">
<path d="M926 300 Q950 284 976 300 Z M948 292 Q944 250 936 214 Q926 176 946 150 Q962 128 974 152 Q990 172 976 204 Q962 246 958 292 Z" fill="#F3ECD8"/>
<path d="M952 286 Q950 240 944 206 M960 286 Q966 236 972 196" stroke="#0A3F78" stroke-width="0.9" fill="none"/>
<g fill="#F3ECD8"><circle cx="938" cy="196" r="2"/><circle cx="982" cy="186" r="2"/><circle cx="930" cy="170" r="1.6"/><circle cx="988" cy="160" r="1.6"/><circle cx="952" cy="132" r="2"/><circle cx="968" cy="128" r="1.5"/></g>
</g></g>

<g class="lm"><g class="art">
<path d="M996 300 L1022 226 L1034 246 L1060 164 L1078 214 L1094 194 L1122 262 L1142 300 Z" fill="#F3ECD8"/>
<path d="M1060 164 L1078 214 L1070 300 L1056 300 Z M1094 194 L1122 262 L1118 300 L1098 300 Z M1022 226 L1034 246 L1030 300 L1020 300 Z" fill="url(#rp-d)" opacity="0.7"/>
</g></g>

<g class="lm"><g class="art">
<path d="M1162 300 Q1195 104 1228 300" stroke="#F3ECD8" stroke-width="7" fill="none"/>
<path d="M1162 300 Q1195 104 1228 300" stroke="#0A3F78" stroke-width="0.8" fill="none"/>
</g></g>

<g class="lm"><g class="art">
<path d="M1232 300 Q1262 230 1296 262 Q1318 236 1342 300 Z" fill="#F3ECD8" opacity="0.55"/>
<path d="M1244 300 Q1276 256 1304 276 Q1324 258 1344 300 Z" fill="#F3ECD8"/>
<path d="M1244 300 Q1276 256 1304 276 Q1324 258 1344 300 Z" fill="url(#rp-h)" opacity="0.45"/>
</g></g>

<g class="lm"><g class="art">
<path d="M1340 300 l-3 -26 M1344 300 l1 -32 M1348 300 l4 -24" stroke="#F3ECD8" stroke-width="1.6" fill="none"/>
<ellipse cx="1362" cy="276" rx="9" ry="5" fill="#F3ECD8"/>
<path d="M1368 274 Q1376 262 1370 252 Q1368 248 1374 247" stroke="#F3ECD8" stroke-width="2.2" fill="none"/>
<path d="M1374 247 l9 2 M1360 281 V300 M1365 281 V300" stroke="#F3ECD8" stroke-width="1.5" fill="none"/>
</g></g>

<g class="lm"><g class="art">
<path d="M1393 294h28v6h-28z M1396 296 L1399 276 L1415 276 L1418 296 Z M1401 276 L1404 244 L1410 244 L1414 276 Z" fill="#F3ECD8"/>
<circle cx="1407" cy="239" r="4" fill="#F3ECD8"/>
<path d="M1403 236 l-3 -4 M1407 235 v-5 M1411 236 l3 -4" stroke="#F3ECD8" stroke-width="1.2" fill="none"/>
<path d="M1409 246 L1415 224" stroke="#F3ECD8" stroke-width="2.4" fill="none"/>
<circle cx="1415.5" cy="221" r="2.8" fill="#F2854A"/>
</g></g>

<g class="lm"><g class="art">
<path d="M1425 258 l-14 -5 M1425 258 l-14 3" stroke="#F3ECD8" stroke-width="0.8" opacity="0.6" fill="none"/>
<path d="M1427 300 L1429.5 262 L1435.5 262 L1438 300 Z M1428 254 L1432.5 248 L1437 254 Z M1429 254h7v8h-7z" fill="#F3ECD8"/>
<path d="M1428 282H1437 M1428.6 272H1436.4" stroke="#0A3F78" stroke-width="1" fill="none"/>
</g></g>

<path d="M0 300 Q200 290 400 298 T800 296 T1200 300 T1440 294 L1440 400 L0 400 Z" fill="#F3ECD8"/>
<path d="M0 300 Q200 290 400 298 T800 296 T1200 300 T1440 294 L1440 400 L0 400 Z" fill="url(#rp-h)" opacity="0.22"/>

<path id="panoTrail" d="M0 384 C200 372 380 396 560 380 S900 368 1080 384 S1320 396 1440 378" stroke="#0A3F78" stroke-width="2" stroke-dasharray="2 9" stroke-linecap="round" fill="none"/>
<path d="M90 396 l-4 -10 M92 396 l0 -12 M94 396 l5 -9 M330 394 l-4 -9 M332 394 l1 -11 M334 394 l5 -8 M612 398 l-4 -10 M614 398 l0 -12 M616 398 l5 -9 M820 394 l-3 -9 M822 394 l1 -11 M824 394 l5 -8 M1150 398 l-4 -10 M1152 398 l0 -12 M1154 398 l5 -9 M1300 394 l-4 -9 M1302 394 l1 -11 M1304 394 l5 -8" stroke="#0A3F78" stroke-width="1.1" stroke-linecap="round" fill="none"/>

<g class="hiker" id="panoHiker" style="transform:translate(707px,336px)" aria-hidden="true">
<circle cx="13" cy="5" r="4" fill="#C8440A"/>
<path d="M13 10V25 M13 25L7 40 M13 25L19 40 M13 14L20 21 M13 14L7 19" stroke="#C8440A" stroke-width="2.6" stroke-linecap="round" fill="none"/>
<path d="M21 14V40" stroke="#C8440A" stroke-width="1.6" stroke-linecap="round" fill="none"/>
<rect x="5.5" y="11" width="6" height="11" rx="2" fill="#C8440A"/>
</g>
</svg>
  </div>
  <div class="pano-pad"></div>
</div>
