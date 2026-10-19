<?php
/* GET /api/rates.php → {base, date, source, rates} */
require dirname(__DIR__, 2) . '/app/inc/config.php';
require VPS_ROOT . '/app/inc/rates.php';

$r = vps_get_rates();
vps_json_out([
    'base' => 'CNY',
    'date' => $r['date'],
    'source' => $r['source'],
    'rates' => $r['rates'],
]);
