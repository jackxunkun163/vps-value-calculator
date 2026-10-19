<?php
/* 分享图（SVG）生成：1200x630，样式复刻自 vps.ss 的 /api/share.php 输出 */

function vps_share_svg(array $r, $rateDate)
{
    $h = 'vps_h';
    $money = 'vps_money';
    $plain = 'vps_plain';

    $big = $money($r['resCNY']);                       // 主值
    $daysTxt = $money($r['days'], $r['isDateTime'] ? 2 : 0);
    $pctTxt = number_format($r['pct'], 1, '.', '');
    $barW = max(0.0, min(1040 * $r['pct'] / 100, 1040));

    /* 副行一：续费 30.00 USD · 年付 · 1 USD = 6.683 CNY */
    $sub1 = '续费 ' . $money($r['amount']) . ' ' . $r['curr'] . ' · ' . $r['cycleLabel'];
    if ($r['curr'] !== 'CNY') {
        $sub1 .= ' · 1 ' . $r['curr'] . ' = ' . $plain($r['rate'], VPS_RATE_DECIMALS) . ' CNY'
            . ($r['customUsed'] ? ' (自定义)' : '');
    }

    /* 副行二：实付 / 溢价 / 总支出 */
    $sub2 = '';
    if ($r['hasPaid']) {
        $positive = $r['prem'] >= 0;
        $sub2 = '    <text x="90" y="392" class="vps-numeric" font-size="19" font-weight="400" fill="#F0FFFB">实付卖家 '
            . '<tspan font-weight="600" fill="#FFFFFF">¥ ' . $h($money($r['actPaid'])) . '</tspan> · '
            . $h($positive ? '卖家溢价 +¥ ' : '卖家折价 -¥ ')
            . '<tspan font-weight="600" fill="#DDFBF3">' . $h($money(abs($r['prem']))) . '</tspan> · 买家总支出 '
            . '<tspan font-weight="600" fill="#FFFFFF">¥ ' . $h($money($r['totalCost'])) . '</tspan></text>';
    }

    $title = 'VPS 剩余价值 ¥' . $big;
    $desc = '剩余 ' . $daysTxt . ' 天，共 ' . $r['cycleDays'] . ' 天，到期 ' . $r['expiryDate']
        . ($r['hasPaid'] ? '，实付 ¥' . $money($r['actPaid']) : '');

    $brand = VPS_BRAND;

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" role="img" aria-labelledby="title desc">
  <title id="title">{$h($title)}</title>
  <desc id="desc">{$h($desc)}</desc>
  <defs>
    <linearGradient id="vps-surface" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#EEF7F4"/>
      <stop offset="1" stop-color="#E6F4EF"/>
    </linearGradient>
    <linearGradient id="vps-hero" x1="0" y1="0" x2="1" y2=".7">
      <stop offset="0" stop-color="#00C4A8"/>
      <stop offset="1" stop-color="#008F7B"/>
    </linearGradient>
    <linearGradient id="vps-progress" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#00C4A8"/>
      <stop offset="1" stop-color="#008F7B"/>
    </linearGradient>
    <linearGradient id="vps-orbit" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#B9F3E8" stop-opacity=".22"/>
      <stop offset="1" stop-color="#B9F3E8" stop-opacity="0"/>
    </linearGradient>
    <filter id="vps-shadow" x="-10%" y="-10%" width="120%" height="130%" color-interpolation-filters="sRGB">
      <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#0A5F52" flood-opacity=".07"/>
    </filter>
    <clipPath id="vps-hero-clip"><rect x="56" y="126" width="728" height="282" rx="22"/></clipPath>
    <clipPath id="vps-progress-clip"><rect x="80" y="474" width="1040" height="10" rx="5"/></clipPath>
  </defs>
  <style>
    .vps-type { font-family: 'DM Sans', 'Inter', 'Helvetica Neue', Arial, 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', 'Noto Sans SC', 'Droid Sans Fallback', sans-serif; }
    .vps-numeric { font-variant-numeric: tabular-nums lining-nums; }
    @media (prefers-reduced-motion: reduce) { .flow-motion { display: none; } }
  </style>

  <rect width="1200" height="630" rx="32" fill="url(#vps-surface)"/>
  <rect x="28" y="26" width="1144" height="542" rx="30" fill="#FFFFFF" filter="url(#vps-shadow)"/>
  <rect x="28.5" y="26.5" width="1143" height="541" rx="29.5" fill="none" stroke="#D8EBE3"/>

  <!-- 品牌头部 -->
  <g aria-hidden="true">
    <rect x="56" y="55" width="46" height="46" rx="14" fill="#00C4A8"/>
    <rect x="67" y="66" width="24" height="9" rx="3" fill="none" stroke="#FFFFFF" stroke-width="1.7"/>
    <rect x="67" y="81" width="24" height="9" rx="3" fill="none" stroke="#FFFFFF" stroke-width="1.7"/>
    <circle cx="72" cy="70.5" r="1.3" fill="#FFFFFF"/>
    <circle cx="72" cy="85.5" r="1.3" fill="#FFFFFF"/>
    <path d="M81 70.5h5 M81 85.5h5" stroke="#FFFFFF" stroke-width="1.7" stroke-linecap="round"/>
    <path d="M1073 71h12 M1073 78h30 M1073 85h48" fill="none" stroke="#CBEAE0" stroke-width="3" stroke-linecap="round"/>
  </g>
  <g class="vps-type">
    <text x="118" y="88" fill="#008F7B"><tspan font-size="31" font-weight="700" letter-spacing="-1">{$h($brand)}</tspan><tspan font-size="21" font-weight="400" fill="#5F7A73"> · 剩余价值计算器</tspan></text>

    <!-- 主值区 -->
    <rect x="56" y="126" width="728" height="282" rx="22" fill="url(#vps-hero)"/>
    <g clip-path="url(#vps-hero-clip)" aria-hidden="true" fill="none" stroke="url(#vps-orbit)">
      <circle cx="782" cy="153" r="66"/>
      <circle cx="782" cy="153" r="100"/>
      <circle cx="782" cy="153" r="134"/>
      <circle cx="782" cy="153" r="168"/>
      <circle cx="782" cy="153" r="202"/>
      <path d="M665 273 830 108 M614 219 813 20" stroke-opacity=".5"/>
      <circle cx="682" cy="153" r="4" fill="#FFFFFF" stroke="none" opacity=".7"/>
    </g>
    <text x="90" y="183" font-size="22" font-weight="400" fill="#F0FFFB">当前剩余价值 (CNY)</text>
    <text x="87" y="294" class="vps-numeric" font-weight="600" fill="#FFFFFF"><tspan font-size="55" fill="#FFFFFF">¥</tspan><tspan font-size="101" letter-spacing="-3">{$h($big)}</tspan></text>
    <path d="M90 329H750" stroke="#FFFFFF" stroke-opacity=".17"/>
    <text x="90" y="358" class="vps-numeric" font-size="20" font-weight="400" fill="#F0FFFB">{$h($sub1)}</text>
{$sub2}

    <!-- 天数卡 -->
    <rect x="800" y="126" width="344" height="132" rx="22" fill="#E4F7F1"/>
    <text x="830" y="167" font-size="20" fill="#4B8072">剩余天数</text>
    <text x="828" y="231" class="vps-numeric" fill="#008F7B"><tspan font-size="59" font-weight="600" letter-spacing="-1.5">{$h($daysTxt)}</tspan><tspan font-size="23" font-weight="400" fill="#4B8072"> 天</tspan></text>
    <g transform="translate(1095 161)" aria-hidden="true" fill="none" stroke="#00A48D" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
      <circle r="12"/>
      <path d="M0-6v6l4.5 3"/>
    </g>

    <rect x="800.5" y="274.5" width="343" height="133" rx="21.5" fill="#F4FBF8" stroke="#DCEEE7"/>
    <text x="830" y="316" font-size="20" fill="#5F7A73">周期天数</text>
    <text x="828" y="380" class="vps-numeric" fill="#087C6B"><tspan font-size="59" font-weight="600" letter-spacing="-1.5">{$h((string)$r['cycleDays'])}</tspan><tspan font-size="23" font-weight="400" fill="#5F7A73"> 天</tspan></text>
    <g transform="translate(1095 309)" aria-hidden="true" fill="none" stroke="#75A99A" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
      <rect x="-11" y="-9" width="22" height="21" rx="4"/>
      <path d="M-5-12v6 M5-12v6 M-11-2h22 M-5 4h3 M3 4h3"/>
    </g>

    <!-- 剩余周期进度 -->
    <text x="80" y="453" font-size="20" font-weight="500" fill="#326D5E">剩余周期</text>
    <text x="1120" y="453" text-anchor="end" class="vps-numeric" font-size="24" font-weight="600" fill="#008F7B">{$h($pctTxt)}%</text>
    <rect x="80" y="474" width="1040" height="10" rx="5" fill="#E0F2EB"/>
    <rect x="80" y="474" width="{$h(number_format($barW, 2, '.', ''))}" height="10" rx="5" fill="url(#vps-progress)"/>
    <g clip-path="url(#vps-progress-clip)" aria-hidden="true">
      <path class="flow-motion" d="M-140 479h80" stroke="#FFFFFF" stroke-width="10" opacity=".22">
        <animateTransform attributeName="transform" type="translate" values="0 0;1320 0" dur="7s" repeatCount="indefinite"/>
      </path>
    </g>
    <text x="80" y="527" class="vps-numeric" font-size="19" fill="#6B8A7F">交易日 <tspan fill="#355F51" font-weight="500">{$h($r['tradeDate'])}</tspan></text>
    <text x="1120" y="527" text-anchor="end" class="vps-numeric" font-size="19" fill="#6B8A7F">到期日 <tspan fill="#355F51" font-weight="500">{$h($r['expiryDate'])}</tspan></text>

    <!-- 署名与汇率日期 -->
    <text x="600" y="606" text-anchor="middle" font-size="17" fill="#5F7A73">由 <tspan font-weight="700" fill="#008F7B">{$h($brand)}</tspan> 提供计算服务</text>
    <text x="1144" y="606" text-anchor="end" class="vps-numeric" font-size="15" fill="#8AA39B">汇率 {$h($rateDate)}</text>
  </g>
</svg>
SVG;
}
