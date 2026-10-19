<?php
/* POST /api/count.php → {today, total}（同源校验 + 计数自增）
   GET  → 仅返回当前计数 */
require dirname(__DIR__, 2) . '/app/inc/config.php';
require VPS_ROOT . '/app/inc/count.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    vps_require_same_origin_post();
    $c = vps_bump_counts();
} else {
    $c = vps_read_counts();
}

vps_json_out(['today' => (int)$c['today'], 'total' => (int)$c['total']]);
