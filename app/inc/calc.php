<?php
/* 核心计算：与前端 assets/app.js 的 calculate() 逻辑保持一致（供分享图服务端复算使用） */

/**
 * 解析并归一化计算参数（来自 GET / POST 表单）。
 * 返回数组；非法值回退为默认。
 */
function vps_parse_params(array $src)
{
    $p = [];
    $p['renewalAmount'] = is_numeric($src['renewalAmount'] ?? '') ? (float)$src['renewalAmount'] : null;
    $p['currency'] = array_key_exists($src['currencySelector'] ?? '', VPS_CURRENCIES)
        ? $src['currencySelector'] : 'USD';
    $p['cycleDays'] = (int)($src['cycleDays'] ?? 365);
    if (!isset(VPS_CYCLES[$p['cycleDays']])) {
        $p['cycleDays'] = 365;
    }
    $p['lastInput'] = ($src['lastInput'] ?? 'actualPaid') === 'premiumInput' ? 'premiumInput' : 'actualPaid';
    $p['isDateTime'] = ($src['isDateTime'] ?? '') === '1';

    $p['tradeDate'] = vps_parse_date($src['tradeDate'] ?? '', $p['isDateTime']);
    $p['expiryDate'] = vps_parse_date($src['expiryDate'] ?? '', $p['isDateTime']);

    $p['actualPaid'] = is_numeric($src['actualPaid'] ?? '') && $src['actualPaid'] !== ''
        ? (float)$src['actualPaid'] : null;
    $p['premium'] = is_numeric($src['premiumInput'] ?? '') && $src['premiumInput'] !== ''
        ? (float)$src['premiumInput'] : null;
    $p['pushFee'] = is_numeric($src['pushFee'] ?? '') && $src['pushFee'] !== ''
        ? (float)$src['pushFee'] : 0.0;
    $p['pushCurrency'] = in_array($src['pushCurrency'] ?? '', VPS_PUSH_CURRENCIES, true)
        ? $src['pushCurrency'] : 'USD';
    $p['pushPayer'] = array_key_exists($src['pushPayer'] ?? '', VPS_PAYER_LABELS)
        ? $src['pushPayer'] : 'buyer';

    $p['useCustomRate'] = ($src['useCustomRate'] ?? '') === '1';
    $p['customRate'] = $p['useCustomRate'] && is_numeric($src['customRateInput'] ?? '') && (float)$src['customRateInput'] > 0
        ? (float)$src['customRateInput'] : null;

    $p['useMiddleman'] = ($src['useMiddleman'] ?? '') === '1';
    $p['middlemanPayer'] = array_key_exists($src['middlemanPayer'] ?? '', VPS_MID_PAYER_LABELS)
        ? $src['middlemanPayer'] : 'buyer';
    return $p;
}

function vps_parse_date($value, $isDateTime)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $fmt = $isDateTime ? 'Y-m-d\TH:i' : 'Y-m-d';
    $d = DateTime::createFromFormat($fmt, $value);
    return $d && $d->format($fmt) === $value ? $d : null;
}

/**
 * 计算。$rates 为对 CNY 汇率表。
 * 返回 null 表示输入不完整（缺金额或日期无效/已到期）。
 */
function vps_calculate(array $p, array $rates)
{
    if ($p['renewalAmount'] === null || $p['renewalAmount'] <= 0
        || !$p['tradeDate'] || !$p['expiryDate']) {
        return null;
    }
    $days = ($p['expiryDate']->getTimestamp() - $p['tradeDate']->getTimestamp()) / 86400;
    if (!is_finite($days) || $days <= 0) {
        return null;
    }

    $cycleDays = $p['cycleDays'];
    $rate = isset($rates[$p['currency']]) ? (float)$rates[$p['currency']] : 1.0;
    $customUsed = false;
    if ($p['customRate'] !== null) {
        $rate = $p['customRate'];
        $customUsed = true;
    }

    $resOrig = ($p['renewalAmount'] / $cycleDays) * $days;
    $resCNY = $resOrig * $rate;

    $push = $p['pushFee'] * (isset($rates[$p['pushCurrency']]) ? (float)$rates[$p['pushCurrency']] : 1.0);
    $payer = $p['pushPayer'];
    $bPush = $payer === 'buyer' ? $push : ($payer === 'split' ? $push / 2 : 0.0);
    $sPush = $payer === 'seller' ? $push : ($payer === 'split' ? $push / 2 : 0.0);

    $actPaid = $p['actualPaid'];
    $prem = $p['premium'];
    $isMid = $p['useMiddleman'];
    $midWho = $p['middlemanPayer'];

    /* 双向反推 */
    if ($p['lastInput'] === 'actualPaid') {
        if ($actPaid !== null) {
            $sMid = 0.0;
            if ($isMid) {
                if ($midWho === 'seller') {
                    $sMid = $actPaid * VPS_MID_FEE;
                } elseif ($midWho === 'split') {
                    $sMid = $actPaid * VPS_MID_FEE / 2;
                }
            }
            $prem = ($actPaid - $sPush - $sMid) - $resCNY;
        }
    } else {
        if ($prem !== null) {
            if ($isMid && $midWho === 'seller') {
                $actPaid = ($resCNY + $prem + $sPush) / (1 - VPS_MID_FEE);
            } elseif ($isMid && $midWho === 'split') {
                $actPaid = ($resCNY + $prem + $sPush) / (1 - VPS_MID_FEE / 2);
            } else {
                $actPaid = $resCNY + $prem + $sPush;
            }
        }
    }

    $bMid = 0.0;
    $midFee = 0.0;
    if ($isMid && $actPaid !== null && $actPaid > 0) {
        $midFee = $actPaid * VPS_MID_FEE;
        if ($midWho === 'buyer') {
            $bMid = $midFee;
        } elseif ($midWho === 'split') {
            $bMid = $midFee / 2;
        }
    }
    $totalCost = ($actPaid === null ? 0.0 : $actPaid) + $bPush + $bMid;
    $pct = min(($days / $cycleDays) * 100, 100);

    return [
        'curr' => $p['currency'],
        'amount' => $p['renewalAmount'],
        'cycleDays' => $cycleDays,
        'cycleLabel' => VPS_CYCLES[$cycleDays],
        'rate' => $rate,
        'customUsed' => $customUsed,
        'tradeDate' => $p['tradeDate']->format($p['isDateTime'] ? 'Y-m-d H:i' : 'Y-m-d'),
        'expiryDate' => $p['expiryDate']->format($p['isDateTime'] ? 'Y-m-d H:i' : 'Y-m-d'),
        'isDateTime' => $p['isDateTime'],
        'days' => $days,
        'pct' => $pct,
        'resOrig' => $resOrig,
        'resCNY' => $resCNY,
        'hasPaid' => $actPaid !== null && $actPaid >= 0,
        'actPaid' => $actPaid,
        'prem' => $prem === null ? 0.0 : $prem,
        'pushFee' => $p['pushFee'],
        'pushCurrency' => $p['pushCurrency'],
        'pushPayer' => $payer,
        'isMid' => $isMid,
        'midWho' => $midWho,
        'midFee' => $midFee,
        'bMid' => $bMid,
        'bPush' => $bPush,
        'totalCost' => $totalCost,
    ];
}
