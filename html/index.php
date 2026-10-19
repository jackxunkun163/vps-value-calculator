<?php
/* VPS 剩余价值计算器 · 页面渲染（界面复刻自 vps.ss，仅供学习交流） */
require dirname(__DIR__) . '/app/inc/config.php';
require VPS_ROOT . '/app/inc/rates.php';
require VPS_ROOT . '/app/inc/count.php';

/* ---------- 解析 GET 参数，服务端预填 ---------- */
$g = $_GET;
$hasParams = count($g) > 0;

$dateFmt = (($g['isDateTime'] ?? '') === '1') ? 'Y-m-d\TH:i' : 'Y-m-d';
$isDateTime = ($g['isDateTime'] ?? '') === '1';

$cycleDays = isset($g['cycleDays']) && isset(VPS_CYCLES[(int)$g['cycleDays']]) ? (int)$g['cycleDays'] : 365;
$prevCycle = isset($g['prevCycle']) && isset(VPS_CYCLES[(int)$g['prevCycle']]) ? (int)$g['prevCycle'] : $cycleDays;
$lastInput = ($g['lastInput'] ?? 'actualPaid') === 'premiumInput' ? 'premiumInput' : 'actualPaid';

$today = new DateTime('today');
$trade = DateTime::createFromFormat($dateFmt, (string)($g['tradeDate'] ?? ''));
if (!$trade) {
    $trade = clone $today;
    if ($isDateTime) {
        $trade->setTime(0, 0);
    }
}
$expiry = DateTime::createFromFormat($dateFmt, (string)($g['expiryDate'] ?? ''));
$cycleChanged = isset($g['cycleDays'], $g['prevCycle']) && $cycleDays !== $prevCycle;
if ($cycleChanged || !$expiry) {
    $expiry = clone $trade;
    $expiry->modify('+' . $cycleDays . ' days');
}

$currency = array_key_exists($g['currencySelector'] ?? '', VPS_CURRENCIES) ? $g['currencySelector'] : 'USD';
$amountVal = is_numeric($g['renewalAmount'] ?? '') ? (string)(float)$g['renewalAmount'] : '';
$paidVal = is_numeric($g['actualPaid'] ?? '') && $g['actualPaid'] !== '' ? (string)(float)$g['actualPaid'] : '';
$premVal = is_numeric($g['premiumInput'] ?? '') && $g['premiumInput'] !== '' ? (string)(float)$g['premiumInput'] : '';
$pushVal = is_numeric($g['pushFee'] ?? '') && $g['pushFee'] !== '' ? (string)(float)$g['pushFee'] : '';

$pushCurrency = in_array($g['pushCurrency'] ?? '', VPS_PUSH_CURRENCIES, true) ? $g['pushCurrency'] : 'USD';
$pushPayer = array_key_exists($g['pushPayer'] ?? '', VPS_PAYER_LABELS) ? $g['pushPayer'] : 'buyer';

$useCustomRate = ($g['useCustomRate'] ?? '') === '1' && $currency !== 'CNY';
$customRateVal = is_numeric($g['customRateInput'] ?? '') && (float)$g['customRateInput'] > 0
    ? (string)(float)$g['customRateInput'] : '';

$useMiddleman = ($g['useMiddleman'] ?? '') === '1';
$middlemanPayer = array_key_exists($g['middlemanPayer'] ?? '', VPS_MID_PAYER_LABELS) ? $g['middlemanPayer'] : 'buyer';

/* ---------- 汇率与计数 ---------- */
$ratesInfo = vps_get_rates();
$rates = $ratesInfo['rates'];
$rateDate = $ratesInfo['date'];
$rateOk = $ratesInfo['source'] === 'live' || $ratesInfo['source'] === 'cache';
$counts = vps_read_counts();

$site = vps_site_url();
$dateType = $isDateTime ? 'datetime-local' : 'date';
$tradeVal = $trade->format($dateFmt);
$expiryVal = $expiry->format($dateFmt);
$ver = VPS_ASSET_VER;

$currencyLabels = [];
foreach (VPS_CURRENCIES as $code => $label) {
    $currencyLabels[$code] = $code . ' · ' . $label;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VPS 剩余价值计算器 - 服务器转让剩余价值/溢价在线计算 | <?php echo VPS_BRAND; ?></title>
<meta name="description" content="VPS 剩余价值计算器：输入续费金额、付款周期与到期日期，自动按天折算 VPS/服务器转让的剩余价值，实时汇率换算为人民币，并支持卖家溢价、Push 手续费、5% 中介担保费的双向反推与总支出核算。">
<meta name="keywords" content="VPS剩余价值计算器,剩余价值计算,VPS转让计算器,服务器剩余价值,VPS溢价计算,按天折算,VPS交易,Push费用,中介担保费,汇率换算">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="author" content="<?php echo VPS_BRAND; ?>">
<meta name="theme-color" content="#00bfa5">
<link rel="canonical" href="<?php echo vps_h($site . '/'); ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo VPS_BRAND; ?>">
<meta property="og:locale" content="zh_CN">
<meta property="og:url" content="<?php echo vps_h($site . '/'); ?>">
<meta property="og:title" content="VPS 剩余价值计算器 - 服务器转让剩余价值/溢价在线计算 | <?php echo VPS_BRAND; ?>">
<meta property="og:description" content="VPS 剩余价值计算器：输入续费金额、付款周期与到期日期，自动按天折算 VPS/服务器转让的剩余价值，实时汇率换算为人民币，并支持卖家溢价、Push 手续费、5% 中介担保费的双向反推与总支出核算。">
<meta property="og:image" content="<?php echo vps_h($site . '/assets/og-cover.png'); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="VPS 剩余价值计算器 - 服务器转让剩余价值/溢价在线计算 | <?php echo VPS_BRAND; ?>">
<meta name="twitter:description" content="VPS 剩余价值计算器：输入续费金额、付款周期与到期日期，自动按天折算 VPS/服务器转让的剩余价值，实时汇率换算为人民币，并支持卖家溢价、Push 手续费、5% 中介担保费的双向反推与总支出核算。">
<meta name="twitter:image" content="<?php echo vps_h($site . '/assets/og-cover.png'); ?>">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/assets/vendor/flatpickr/flatpickr.min.css?v=4.6.13">
<link rel="stylesheet" href="/assets/style.css?v=<?php echo $ver; ?>">
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "WebApplication",
            "@id": "<?php echo vps_h($site . '/#app'); ?>",
            "name": "VPS 剩余价值计算器",
            "url": "<?php echo vps_h($site . '/'); ?>",
            "description": "VPS 剩余价值计算器：输入续费金额、付款周期与到期日期，自动按天折算 VPS/服务器转让的剩余价值，实时汇率换算为人民币，并支持卖家溢价、Push 手续费、5% 中介担保费的双向反推与总支出核算。",
            "applicationCategory": "FinanceApplication",
            "operatingSystem": "Any",
            "inLanguage": "zh-CN",
            "isAccessibleForFree": true,
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "CNY"
            },
            "featureList": [
                "按天折算 VPS 剩余价值",
                "实时汇率换算人民币",
                "卖家溢价双向反推",
                "Push 手续费分摊",
                "5% 中介担保费核算",
                "一键复制文本 / Markdown 表格",
                "生成 SVG 结果分享图"
            ],
            "publisher": {
                "@type": "Organization",
                "name": "<?php echo VPS_BRAND; ?>",
                "url": "<?php echo vps_h($site . '/'); ?>"
            }
        },
        {
            "@type": "FAQPage",
            "@id": "<?php echo vps_h($site . '/#faq'); ?>",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "VPS 剩余价值怎么计算？",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "剩余价值 = 续费金额 ÷ 付款周期天数 × 剩余天数，再按当前汇率换算为人民币。例如年付 30 美元、还剩 200 天，日均约 0.0822 美元，剩余价值约 16.44 美元。"
                    }
                },
                {
                    "@type": "Question",
                    "name": "卖家溢价和实付金额是什么关系？",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "溢价 = 实付卖家金额 − 剩余价值 − 卖家承担的 Push 费与中介费。计算器支持双向反推：填实付金额自动算出溢价，填溢价自动算出应付金额。"
                    }
                },
                {
                    "@type": "Question",
                    "name": "中介担保费和 Push 手续费如何分摊？",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "中介担保费按成交额的 5% 计算，可选择买家付、卖家付或双方 AA 平摊；Push 手续费同样支持三种分摊方式，并按所选币种的实时汇率折算为人民币计入总支出。"
                    }
                },
                {
                    "@type": "Question",
                    "name": "汇率数据多久更新一次？",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "汇率由服务端每小时从公开汇率接口拉取一次并缓存，页面展示保留 3 位小数。如需按自己的收款渠道结算，可勾选「自定义」手动填写汇率。"
                    }
                }
            ]
        }
    ]
}</script>
</head>
<body>

<div class="toast" id="toast" role="status" aria-live="polite">
    <i class="fas fa-circle-check"></i><span id="toastMsg"></span>
</div>

<div class="wrap">
    <header class="topbar">
        <div class="brand">
            <div class="brand-logo" aria-hidden="true"><i class="fas fa-server"></i></div>
            <div>
                <h1>VPS 剩余价值计算器</h1>
                <p class="tagline">Remaining Value Calculator · <?php echo VPS_BRAND; ?></p>
            </div>
        </div>
        <div class="stats">
            <div class="today"><i class="fas fa-calendar-day"></i> 今日计算 <span id="todayCount"><?php echo (int)$counts['today']; ?></span></div>
            <div class="total"><i class="fas fa-fire"></i> 累计计算 <span id="totalCount"><?php echo (int)$counts['total']; ?></span></div>
        </div>
    </header>

    <form class="layout" id="calcForm" method="get" action="/">
        <input type="hidden" name="cycleDays" id="cycleDaysInput" value="<?php echo $cycleDays; ?>">
        <input type="hidden" name="prevCycle" id="prevCycleInput" value="<?php echo $cycleDays; ?>">
        <input type="hidden" name="lastInput" id="lastInputField" value="<?php echo vps_h($lastInput); ?>">
        <input type="hidden" name="isDateTime" id="isDateTimeField" value="<?php echo $isDateTime ? '1' : ''; ?>">
        <input type="hidden" name="pushCurrency" id="pushCurrencyField" value="<?php echo vps_h($pushCurrency); ?>">

        <!-- 左侧：参数配置 -->
        <div class="col col-form">
            <section class="card" aria-labelledby="sec-renew">
                <p class="section-label" id="sec-renew">① 续费信息</p>
                <div class="grid-renew">
                    <div>
                        <label class="field-label" for="renewalAmount">续费金额</label>
                        <input type="number" step="any" min="0" inputmode="decimal" id="renewalAmount" name="renewalAmount"
                               class="field-input" placeholder="请输入金额" value="<?php echo vps_h($amountVal); ?>">
                    </div>
                    <div>
                        <div class="field-label row">
                            <label for="currencySelector">计价币种</label>
                            <label class="mini-toggle" id="customRateToggleBox">
                                <input type="checkbox" id="useCustomRate" name="useCustomRate" value="1"<?php echo $useCustomRate ? ' checked' : ''; ?>> 自定义
                            </label>
                        </div>
                        <div class="csel-wrap csel-block">
                            <button type="button" class="csel-btn" id="currencySelectorBtn" aria-haspopup="listbox" aria-expanded="false" aria-label="计价币种">
                                <span id="currencySelectorLabel"><?php echo vps_h($currencyLabels[$currency]); ?></span> <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="csel-dropdown" id="currencySelectorDropdown" role="listbox">
<?php foreach ($currencyLabels as $code => $label): ?>
                                <div class="csel-option<?php echo $code === $currency ? ' active' : ''; ?>" role="option" data-value="<?php echo $code; ?>" data-label="<?php echo vps_h($label); ?>"><?php echo vps_h($label); ?></div>
<?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="currencySelector" name="currencySelector" value="<?php echo vps_h($currency); ?>">
                        <div id="customRateWrapper" class="custom-rate<?php echo $useCustomRate ? '' : ' hidden'; ?>">
                            <span>1 <span id="customRateLabel"><?php echo vps_h($currency); ?></span> =</span>
                            <input type="number" step="any" min="0" inputmode="decimal" id="customRateInput" name="customRateInput"
                                   class="field-input" placeholder="汇率" value="<?php echo vps_h($customRateVal); ?>" aria-label="自定义汇率">
                            <span>CNY</span>
                        </div>
                    </div>
                </div>

                <p class="field-label">付款周期</p>
                <div class="grid-cycle" id="cycleSelector">
<?php foreach (VPS_CYCLES as $days => $label): ?>
                    <button type="submit" name="cycleDays" value="<?php echo $days; ?>"
                            class="cycle-btn<?php echo $days === $cycleDays ? ' active' : ''; ?>" data-cycle="<?php echo $days; ?>"><?php echo vps_h($label); ?></button>
<?php endforeach; ?>
                </div>
            </section>

            <section class="card" aria-labelledby="sec-time">
                <div class="card-head">
                    <p class="section-label" id="sec-time">② 时间信息</p>
                    <button type="button" id="toggleTimePrecision" class="link-btn<?php echo $isDateTime ? ' on' : ''; ?>">
                        <i class="fas fa-clock"></i> 精确到时分
                    </button>
                </div>
                <div class="grid-2">
                    <div>
                        <label class="field-label" for="tradeDate" id="tradeDateLabel">交易日期</label>
                        <div class="date-field">
                            <input type="<?php echo $dateType; ?>" id="tradeDate" name="tradeDate"
                                   class="field-input" value="<?php echo vps_h($tradeVal); ?>" autocomplete="off">
                            <i class="fas fa-calendar-days" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="expiryDate" id="expiryDateLabel">到期日期</label>
                        <div class="date-field">
                            <input type="<?php echo $dateType; ?>" id="expiryDate" name="expiryDate"
                                   class="field-input" value="<?php echo vps_h($expiryVal); ?>" autocomplete="off">
                            <i class="fas fa-calendar-days" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card" aria-labelledby="sec-settle">
                <p class="section-label" id="sec-settle">③ 交易结算</p>
                <div class="settle-box">
                    <div class="grid-2">
                        <div>
                            <label class="settle-label" for="actualPaid">实付卖家 (CNY)</label>
                            <input type="number" step="any" min="0" inputmode="decimal" id="actualPaid" name="actualPaid"
                                   class="field-input core-input" value="<?php echo vps_h($paidVal); ?>">
                        </div>
                        <div>
                            <label class="settle-label" for="premiumInput">卖家溢价 (CNY)</label>
                            <input type="number" step="any" inputmode="decimal" id="premiumInput" name="premiumInput"
                                   class="field-input core-input" value="<?php echo vps_h($premVal); ?>">
                        </div>
                    </div>
                    <div id="bindHint" class="bind-hint">
                        <i class="fas fa-lock"></i>
                        当前锁定【<?php echo $lastInput === 'actualPaid' ? '实付卖家' : '卖家溢价'; ?>】<?php echo $lastInput === 'actualPaid' ? '推算溢价' : '推算实付'; ?>                    </div>
                </div>

                <div class="push-row">
                    <div class="push-title">Push 费</div>
                    <div class="push-controls">
                        <input type="number" step="any" min="0" inputmode="decimal" id="pushFee" name="pushFee"
                               class="field-input push-fee" placeholder="0" value="<?php echo vps_h($pushVal); ?>" aria-label="Push 手续费">
                        <div class="csel-wrap">
                            <button type="button" class="csel-btn" id="pushCurrBtn" aria-haspopup="listbox" aria-expanded="false">
                                <span id="pushCurrLabel"><?php echo vps_h($pushCurrency); ?></span> <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="csel-dropdown" id="pushCurrDropdown" role="listbox">
<?php foreach (VPS_PUSH_CURRENCIES as $code): ?>
                                <div class="csel-option<?php echo $code === $pushCurrency ? ' active' : ''; ?>" role="option" data-value="<?php echo $code; ?>"><?php echo $code; ?></div>
<?php endforeach; ?>
                            </div>
                        </div>
                        <div class="csel-wrap">
                            <button type="button" class="csel-btn" id="pushPayerBtn" aria-haspopup="listbox" aria-expanded="false" aria-label="Push 费承担方">
                                <span id="pushPayerLabel"><?php echo vps_h(VPS_PAYER_LABELS[$pushPayer]); ?></span> <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="csel-dropdown" id="pushPayerDropdown" role="listbox">
<?php foreach (VPS_PAYER_LABELS as $key => $label): ?>
                                <div class="csel-option<?php echo $key === $pushPayer ? ' active' : ''; ?>" role="option" data-value="<?php echo $key; ?>" data-label="<?php echo vps_h($label); ?>"><?php echo vps_h($label); ?></div>
<?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="pushPayer" name="pushPayer" value="<?php echo vps_h($pushPayer); ?>">
                    </div>
                </div>

                <noscript>
                    <div class="noscript-submit">
                        <button type="submit" class="btn btn-primary">开始计算</button>
                    </div>
                </noscript>
            </section>

            <section class="card" aria-labelledby="sec-rate">
                <div class="rate-head">
                    <p class="rate-title" id="sec-rate"><i class="fas fa-chart-area"></i>实时汇率 (对 CNY)</p>
                    <div class="rate-meta">
                        <span class="badge<?php echo $rateOk ? '' : ' warn'; ?>" id="rateStatus">
                            <i class="fas fa-<?php echo $rateOk ? 'check' : 'triangle-exclamation'; ?>"></i>
                            <?php echo $rateOk ? vps_h($rateDate) . ' 已更新' : '接口异常 · 使用备用汇率'; ?>
                        </span>
                        <span class="rate-source">CURRENCY-API</span>
                    </div>
                </div>
                <div class="rate-grid" id="rateGrid">
<?php foreach (VPS_RATE_GRID as $code): ?>
<?php if (isset($rates[$code])): ?>
                    <div class="rate-cell<?php echo $code === $currency ? ' is-base' : ''; ?>">
                        <div class="code"><?php echo $code; ?></div>
                        <div class="value"><?php echo number_format((float)$rates[$code], VPS_RATE_DECIMALS); ?></div>
                    </div>
<?php endif; ?>
<?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- 右侧：结果面板（浅色） -->
        <div class="col col-result">
            <a class="esim-banner" href="https://esim.now/zh/esim/china?utm_source=vps.ss&utm_medium=banner" target="_blank" rel="noopener">
                <div class="esim-banner__body">
                    <div class="esim-banner__title">eSIM.Now Anywhere</div>
                    <div class="esim-banner__desc">旅行 eSIM 流量套餐——在线购买，几分钟即可安装，无需VPN，即可访问Google。</div>
                </div>
                <span class="esim-banner__cta">立即获取 eSIM</span>
            </a>

            <aside class="result-panel" aria-live="polite">
                <div class="result-hero">
                    <p class="hero-label">VPS 剩余价值 (CNY)</p>
                    <div class="hero-value" id="resCNYBig">¥ --</div>
                </div>

                <div class="premium-box hidden" id="premiumBox">
                    <p class="premium-title is-premium" id="premiumTitle">卖家溢价</p>
                    <div class="premium-value is-premium" id="premiumValueBig">--</div>
                </div>

                <div class="result-rows">
                    <div class="result-row"><span class="result-label">折合原币</span><span class="result-value" id="resOrig">--</span></div>
                    <div class="result-row"><span class="result-label">实付卖家</span><span class="result-value" id="resActual">¥ --</span></div>
                    <div class="result-row hidden" id="middlemanRow">
                        <span class="result-label" id="middlemanRowLabel">买家中介费</span>
                        <span class="result-value soft" id="resMiddleman">¥ --</span>
                    </div>
                    <div class="result-row"><span class="result-label">官方日均</span><span class="result-value" id="dayOfficial">--</span></div>
                    <div class="result-row accent"><span class="result-label">综合日均</span><span class="result-value" id="dayActual">--</span></div>
                </div>

                <div class="progress-block">
                    <div class="progress-head">
                        <span class="p-title">剩余有效期</span>
                        <span class="p-days" id="remainingDays">-- 天</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-bar" id="remainingProgress" style="width:0.00%"></div>
                    </div>
                    <div class="progress-hint" id="progressHint"></div>
                </div>

                <div class="ratio-block hidden" id="valueRatioBox">
                    <div class="ratio-title">价值构成比例</div>
                    <div class="ratio-track">
                        <div class="bar-value" id="barValue" style="width:0.00%"></div>
                        <div class="bar-premium" id="barPremium" style="width:0.00%"></div>
                    </div>
                    <div class="ratio-legend">
                        <div><span class="dot dot-value"></span>剩余价值</div>
                        <div><span class="dot dot-premium"></span>溢价部分</div>
                    </div>
                </div>

                <div class="settle-block">
                    <div class="settle-head">
                        <label class="check-label" for="useMiddleman">
                            <input type="checkbox" id="useMiddleman" name="useMiddleman" value="1"<?php echo $useMiddleman ? ' checked' : ''; ?>>
                            开启中介担保 (5%)
                        </label>
                        <div class="csel-wrap">
                            <button type="button" class="csel-btn" id="middlemanPayerBtn" aria-haspopup="listbox" aria-expanded="false" aria-label="中介费承担方">
                                <span id="middlemanPayerLabel"><?php echo vps_h(VPS_MID_PAYER_LABELS[$middlemanPayer]); ?></span> <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="csel-dropdown" id="middlemanPayerDropdown" role="listbox">
<?php foreach (VPS_MID_PAYER_LABELS as $key => $label): ?>
                                <div class="csel-option<?php echo $key === $middlemanPayer ? ' active' : ''; ?>" role="option" data-value="<?php echo $key; ?>" data-label="<?php echo vps_h($label); ?>"><?php echo vps_h($label); ?></div>
<?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="middlemanPayer" name="middlemanPayer" value="<?php echo vps_h($middlemanPayer); ?>">
                    </div>

                    <div class="final-box" id="finalCostBox">
                        <div class="final-head">
                            <span class="final-label" id="finalCostLabel">买家最终总支出</span>
                            <span class="final-extra" id="extraFeeDetail">含额外支出 ¥ 0.00</span>
                        </div>
                        <div class="final-value" id="finalTotalCostDisplay">¥ --</div>
                    </div>
                </div>

                <div class="actions">
                    <div class="menu-wrap">
                        <button type="button" class="btn btn-primary" id="copyBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="copyMenu">
                            <i class="fas fa-copy"></i> 复制详情 <i class="fas fa-chevron-down caret" aria-hidden="true"></i>
                        </button>
                        <div class="csel-dropdown menu-dropdown" id="copyMenu" role="menu" aria-label="复制格式">
                            <button type="button" class="csel-option" role="menuitem" data-format="text"><i class="fas fa-align-left"></i> 纯文本</button>
                            <button type="button" class="csel-option" role="menuitem" data-format="markdown"><i class="fab fa-markdown"></i> Markdown 表格</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-share" id="shareImgBtn" title="生成 SVG 分享图"><i class="fas fa-image"></i> 分享图片</button>
                    <button type="button" class="btn btn-ghost btn-icon" id="shareBtn" title="复制分享链接" aria-label="复制分享链接"><i class="fas fa-link"></i></button>
                    <button type="button" class="btn btn-danger btn-icon" id="resetBtn" title="重置" aria-label="重置"><i class="fas fa-rotate-right"></i></button>
                </div>
            </aside>
        </div>
    </form>

    <!-- SEO 内容区 -->
    <section class="card prose" id="guide">
        <h2>VPS 剩余价值怎么算？一条公式说清楚</h2>
        <p class="formula">剩余价值 = 续费金额 ÷ 付款周期天数 × 剩余天数 × 汇率</p>
        <p>在 VPS / 独服的二手交易里，买家真正买到的是「尚未使用的服务时长」。因此定价的基准不是原价，而是把续费价格按天摊平后，乘以从交易日到到期日之间的剩余天数。本站的计算器会自动完成折算、汇率换算、溢价反推与手续费分摊，全部计算在服务端与浏览器内完成，不保存你的交易数据。</p>

        <h3>四步得到结果</h3>
        <ol>
            <li><strong>填续费信息</strong>：输入商家标注的续费价格，选择计价币种与付款周期（月付 / 季付 / 半年 / 年付 / 两年 / 三年）。</li>
            <li><strong>填时间信息</strong>：默认交易日期为今天，到期日期按周期自动推算，也可点开日历选择或直接输入；点击「精确到时分」可按小时结算短周期机器。</li>
            <li><strong>填结算金额</strong>：填写「实付卖家」自动反推溢价，或填写「卖家溢价」自动反推应付金额，两个方向随时切换。</li>
            <li><strong>加上附加费用</strong>：按需填写 Push 手续费与 5% 中介担保费，并选择由买家承担、卖家承担还是双方 AA 平摊。</li>
        </ol>

        <h3>计算示例</h3>
        <p>某机器年付 30 USD，交易当天距到期还有 200 天，按 1 USD = 6.683 CNY 计算：日均 30 ÷ 365 ≈ 0.0822 USD，剩余价值 ≈ 16.44 USD ≈ 109.86 CNY。若卖家要价高于该数值，差额即为溢价，常见于 NAT 小鸡、优惠年付机、稀缺 IP 段等有额外附加值的机器。</p>

        <h3>常见问题</h3>
        <h4>溢价一定是坏事吗？</h4>
        <p>不一定。绝版促销价、可续费的低价年付、优质 IP 段、特殊线路（CN2 GIA、9929、AS4837 直连）都可能让机器的实际价值高于纯剩余价值。反之，接近到期、无法续费或存在 ToS 风险的机器，出现折价（负溢价）也很正常。</p>
        <h4>汇率用哪一个？</h4>
        <p>页面默认使用每小时更新一次的公开汇率，保留 3 位小数展示。如果你和对方约定按支付宝、PayPal 或某交易所的汇率结算，勾选「自定义」手动填写即可，计算结果会立刻按你的汇率重算。</p>
        <h4>数据会被上传吗？</h4>
        <p>不会主动保存你的输入。金额、日期等参数只在你本机与本次请求中使用；点击「分享链接」时才会把当前参数写进 URL；点击「分享图片」时会把本次计算结果生成一张 SVG 图片保存在服务器，得到可长期访问的图片链接，方便你把结算明细发给交易对方核对。</p>
    </section>

    <footer class="site-footer">
        <p>© <?php echo date('Y'); ?> <a href="<?php echo vps_h($site . '/'); ?>"><?php echo VPS_BRAND; ?></a> · VPS 剩余价值计算器 · 汇率更新于 <?php echo vps_h($rateDate); ?> · 结果仅供交易参考</p>
    </footer>
</div>

<!-- 分享图弹窗 -->
<div class="modal" id="shareModal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle" aria-hidden="true">
    <div class="modal-backdrop" data-close></div>
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="shareModalTitle"><i class="fas fa-image"></i>分享图片</h2>
            <button type="button" class="modal-close" data-close aria-label="关闭"><i class="fas fa-xmark"></i></button>
        </div>
        <a class="share-preview" id="sharePreviewLink" href="#" target="_blank" rel="noopener" title="在新窗口打开图片">
            <img id="sharePreviewImg" src="" alt="VPS 剩余价值分享图" width="1200" height="630">
        </a>
        <div class="share-row">
            <span class="share-key"><i class="fab fa-markdown"></i>Markdown</span>
            <input type="text" readonly class="share-input" id="shareMdInput" aria-label="Markdown 图片代码">
            <button type="button" class="btn btn-primary btn-sm" data-copy="shareMdInput" data-msg="Markdown 图片代码已复制"><i class="fas fa-copy"></i> 复制</button>
        </div>
        <div class="share-row">
            <span class="share-key"><i class="fas fa-link"></i>图片链接</span>
            <input type="text" readonly class="share-input" id="shareUrlInput" aria-label="图片链接">
            <button type="button" class="btn btn-primary btn-sm" data-copy="shareUrlInput" data-msg="图片链接已复制"><i class="fas fa-copy"></i> 复制</button>
        </div>
        <p class="share-note">图片已保存在服务器，可直接粘贴到论坛、群聊或帖子中；相同结果只会生成一张图。</p>
    </div>
</div>

<script>
window.VPS = {
    rates: <?php echo json_encode($rates); ?>,
    rateDate: <?php echo json_encode($rateDate); ?>,
    rateDecimals: <?php echo VPS_RATE_DECIMALS; ?>,
    midFee: <?php echo json_encode(VPS_MID_FEE); ?>,
    brand: <?php echo json_encode(VPS_BRAND); ?>,
    siteUrl: <?php echo json_encode($site . '/'); ?>,
    cycles: <?php echo json_encode(VPS_CYCLES); ?>,
    pushPayerLabels: <?php echo json_encode(VPS_PAYER_LABELS); ?>,
    rateGrid: <?php echo json_encode(VPS_RATE_GRID); ?>,
    initialCycle: <?php echo $cycleDays; ?>,
    hasParams: <?php echo $hasParams ? 'true' : 'false'; ?>};
</script>
<script src="/assets/vendor/flatpickr/flatpickr.min.js?v=4.6.13" defer></script>
<script src="/assets/vendor/flatpickr/zh.js?v=4.6.13" defer></script>
<script src="/assets/app.js?v=<?php echo $ver; ?>" defer></script>
</body>
</html>
