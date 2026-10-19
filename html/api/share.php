<?php
/* POST /api/share.php → 生成 SVG 分享图并保存，返回 {ok, id, url, title, markdown, today, total} */
require dirname(__DIR__, 2) . '/app/inc/config.php';
require VPS_ROOT . '/app/inc/rates.php';
require VPS_ROOT . '/app/inc/count.php';
require VPS_ROOT . '/app/inc/calc.php';
require VPS_ROOT . '/app/inc/share_svg.php';

vps_require_same_origin_post();

$params = vps_parse_params($_POST);
$ratesInfo = vps_get_rates();
$r = vps_calculate($params, $ratesInfo['rates']);

if ($r === null) {
    vps_json_out(['ok' => false, 'message' => '请先填写续费金额与有效日期'], 422);
}

/* 相同参数复用同一张图：id 取自参数摘要 */
unset($params['tradeDate'], $params['expiryDate']);
ksort($params);
$id = substr(sha1(json_encode($params, JSON_UNESCAPED_UNICODE) . '|' . json_encode($r, JSON_UNESCAPED_UNICODE)), 0, 8);

vps_ensure_dirs();
$file = VPS_SHARE_DIR . '/' . $id . '.svg';
if (!is_file($file)) {
    $svg = vps_share_svg($r, $ratesInfo['date']);
    if (@file_put_contents($file, $svg, LOCK_EX) === false) {
        vps_json_out(['ok' => false, 'message' => '生成分享图失败，请稍后重试'], 500);
    }
}

$counts = vps_bump_counts();
$site = rtrim(vps_site_url(), '/');
$url = $site . '/share/' . $id . '.svg';
$title = 'VPS 剩余价值 ¥' . vps_money($r['resCNY'])
    . ' · 剩余 ' . vps_money($r['days'], $r['isDateTime'] ? 2 : 0) . ' 天';

vps_json_out([
    'ok' => true,
    'id' => $id,
    'url' => $url,
    'title' => $title,
    'markdown' => '![' . $title . '](' . $url . ')',
    'today' => (int)$counts['today'],
    'total' => (int)$counts['total'],
]);
