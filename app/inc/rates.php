<?php
/* 汇率获取与缓存：每小时从公开接口拉取一次，失败时退回缓存 / 内置备用汇率 */

function vps_rates_cache_file()
{
    return VPS_DATA_DIR . '/rates.json';
}

function vps_http_get_json($url, $timeout = 6)
{
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header' => "User-Agent: VPS-Value-Calculator/1.0\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/* 主源：open.er-api.com（含 TWD/KRW 等，免密钥） */
function vps_fetch_rates_primary()
{
    $data = vps_http_get_json('https://open.er-api.com/v6/latest/CNY');
    if (!$data || ($data['result'] ?? '') !== 'success' || !isset($data['rates'])) {
        return null;
    }
    $rates = [];
    foreach (VPS_FALLBACK_RATES as $code => $_) {
        if ($code === 'CNY') {
            $rates['CNY'] = 1.0;
            continue;
        }
        $per = $data['rates'][$code] ?? null;   // 1 CNY = X CODE
        if (!is_numeric($per) || (float)$per <= 0) {
            return null;
        }
        $rates[$code] = 1.0 / (float)$per;
    }
    return $rates;
}

/* 备源：@fawazahmed0/currency-api（CDN，每日更新） */
function vps_fetch_rates_secondary()
{
    $data = vps_http_get_json('https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/cny.json');
    if (!$data || !isset($data['cny'])) {
        return null;
    }
    $rates = [];
    foreach (VPS_FALLBACK_RATES as $code => $_) {
        if ($code === 'CNY') {
            $rates['CNY'] = 1.0;
            continue;
        }
        $per = $data['cny'][strtolower($code)] ?? null;
        if (!is_numeric($per) || (float)$per <= 0) {
            return null;
        }
        $rates[$code] = 1.0 / (float)$per;
    }
    return $rates;
}

/**
 * 返回 ['rates' => [...], 'date' => 'Y-m-d', 'source' => 'live'|'cache'|'fallback']
 * live   = 刚刚拉取成功
 * cache  = 缓存命中（含拉取失败时退回的过期缓存）
 * fallback = 无任何缓存且接口失败，使用内置备用汇率
 */
function vps_get_rates()
{
    vps_ensure_dirs();
    $file = vps_rates_cache_file();
    $cache = null;
    if (is_file($file)) {
        $cache = json_decode((string)@file_get_contents($file), true);
        if (!is_array($cache) || !isset($cache['rates'], $cache['fetched'])) {
            $cache = null;
        }
    }

    $fresh = $cache && (time() - (int)$cache['fetched']) < VPS_RATE_TTL;
    if (!$fresh) {
        $rates = vps_fetch_rates_primary();
        if (!$rates) {
            $rates = vps_fetch_rates_secondary();
        }
        if ($rates) {
            $payload = [
                'fetched' => time(),
                'date' => date('Y-m-d'),
                'rates' => $rates,
            ];
            @file_put_contents($file, json_encode($payload), LOCK_EX);
            return ['rates' => $rates, 'date' => $payload['date'], 'source' => 'live'];
        }
    }

    if ($cache) {
        return ['rates' => $cache['rates'], 'date' => $cache['date'], 'source' => 'cache'];
    }

    return ['rates' => VPS_FALLBACK_RATES, 'date' => date('Y-m-d'), 'source' => 'fallback'];
}
