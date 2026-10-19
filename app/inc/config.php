<?php
/* VPS 剩余价值计算器 · 公共配置与工具
   代码复刻自 vps.ss，仅供学习交流 */

date_default_timezone_set('Asia/Shanghai');

define('VPS_BRAND', 'VPS.ss');
define('VPS_MID_FEE', 0.05);            // 中介担保费比例
define('VPS_RATE_DECIMALS', 3);         // 汇率展示位数
define('VPS_RATE_TTL', 3600);           // 汇率缓存有效期（秒）
define('VPS_ASSET_VER', '1.0.0');

/* 仓库根目录：app/inc/../.. （容器内为 /var/www，本地为仓库根） */
define('VPS_ROOT', dirname(__DIR__, 2));
define('VPS_DATA_DIR', VPS_ROOT . '/data');
define('VPS_SHARE_DIR', dirname(__DIR__) . '/../html/share');

/* 计价币种（与原站下拉一致） */
const VPS_CURRENCIES = [
    'USD' => '美元',
    'CNY' => '人民币',
    'EUR' => '欧元',
    'GBP' => '英镑',
    'JPY' => '日元',
    'HKD' => '港币',
    'TWD' => '新台币',
    'CAD' => '加元',
];

/* Push 费币种下拉顺序（与原站一致） */
const VPS_PUSH_CURRENCIES = ['CNY', 'USD', 'EUR', 'GBP', 'JPY', 'HKD', 'TWD', 'KRW', 'SGD', 'AUD', 'CAD'];

/* 付款周期（天数 => 标签） */
const VPS_CYCLES = [30 => '月付', 91 => '季付', 182 => '半年', 365 => '年付', 730 => '两年', 1095 => '三年'];

/* 汇率面板展示顺序 */
const VPS_RATE_GRID = ['USD', 'EUR', 'GBP', 'JPY', 'HKD', 'TWD', 'KRW', 'SGD', 'AUD', 'CAD'];

const VPS_PAYER_LABELS = ['buyer' => '买家付', 'seller' => '卖家付', 'split' => 'AA平摊'];
const VPS_MID_PAYER_LABELS = ['buyer' => '买家付中介', 'seller' => '卖家付中介', 'split' => 'AA平摊中介'];

/* 接口全部失效时的内置备用汇率（对 CNY） */
const VPS_FALLBACK_RATES = [
    'USD' => 6.683153, 'CNY' => 1.0, 'EUR' => 7.504814, 'GBP' => 8.906181,
    'JPY' => 0.04228, 'HKD' => 0.85161, 'TWD' => 0.205578, 'CAD' => 4.732522,
    'KRW' => 0.005018, 'SGD' => 5.223564, 'AUD' => 4.661325,
];

/* 金额格式化：与前端 toLocaleString('en-US') 一致 */
function vps_money($n, $d = 2)
{
    if (!is_finite((float)$n)) {
        return '--';
    }
    return number_format((float)$n, (int)$d, '.', ',');
}

function vps_plain($n, $d = 2)
{
    return is_finite((float)$n) ? number_format((float)$n, (int)$d, '.', '') : '--';
}

function vps_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* 站点自身 URL（分享链接使用，兼容反代 https） */
function vps_site_url()
{
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

function vps_json_out($data, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* POST 防护：浏览器同源 fetch 会携带 Origin / Referer，缺失或跨域一律拒绝 */
function vps_require_same_origin_post()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        vps_json_out(['error' => 'forbidden'], 403);
    }
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        if (!empty($_SERVER[$key])) {
            $parts = parse_url($_SERVER[$key]);
            $originHost = $parts['host'] ?? '';
            if (strcasecmp($originHost, $host) === 0) {
                return;
            }
        }
    }
    vps_json_out(['error' => 'forbidden'], 403);
}

function vps_ensure_dirs()
{
    foreach ([VPS_DATA_DIR, VPS_SHARE_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
