<?php
/**
 * Official Square App Icons & Brand Assets cho 12 Ngân hàng & Ví điện tử Việt Nam.
 * Sử dụng App Icon chính thức độ phân giải cao (512x512) từ App Store của từng ngân hàng:
 * VCB Digibank, MoMo, Techcombank Mobile, MB Bank, BIDV SmartBanking, ACB ONE,
 * VPBank NEO, TPBank Mobile, VietinBank iPay, Agribank Plus, Sacombank mBanking, ZaloPay.
 */

function bankLogoSvg(string $code, int $size = 46): string
{
    $c = strtolower(preg_replace('/[^A-Za-z]/', '', trim($code)));
    $known = ['vcb', 'momo', 'tcb', 'mb', 'bidv', 'acb', 'vpb', 'tpb', 'vtb', 'agr', 'stb', 'zalopay'];

    if (in_array($c, $known, true)) {
        $file = __DIR__ . '/../assets/banks/' . $c . '-icon.jpg';
        $ver = is_file($file) ? filemtime($file) : 1;
        $alt = htmlspecialchars(strtoupper($c), ENT_QUOTES, 'UTF-8');
        return '<span class="bank-app-icon-wrap" style="width:' . $size . 'px;height:' . $size . 'px">'
            . '<img src="assets/banks/' . $c . '-icon.jpg?v=' . $ver . '" alt="' . $alt . '" class="bank-app-icon-img" loading="lazy" decoding="async">'
            . '</span>';
    }

    // Fallback cho ngân hàng khác
    return <<<SVG
<span class="bank-app-icon-wrap is-fallback" style="width:{$size}px;height:{$size}px">
  <svg width="24" height="24" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M24 8 L40 17 L8 17 Z" fill="#0284C7"/>
    <rect x="13" y="20" width="4" height="13" rx="1.5" fill="#0284C7"/>
    <rect x="22" y="20" width="4" height="13" rx="1.5" fill="#0284C7"/>
    <rect x="31" y="20" width="4" height="13" rx="1.5" fill="#0284C7"/>
    <rect x="9" y="35" width="30" height="4" rx="2" fill="#0F172A"/>
  </svg>
</span>
SVG;
}

/**
 * Watermark chìm nhẹ ở góc nền thẻ.
 */
function bankWatermarkSvg(string $code): string
{
    $c = strtoupper(trim($code));
    switch ($c) {
        case 'VCB':
            return '<svg class="card-watermark" viewBox="0 0 100 100" fill="none">'
                . '<path d="M50 82 C36 82, 16 48, 22 28 C27 14, 73 14, 78 28 C84 48, 64 82, 50 82 Z" stroke="currentColor" stroke-width="12" stroke-linejoin="round"/>'
                . '</svg>';
        case 'TCB':
            return '<svg class="card-watermark" viewBox="0 0 100 100" fill="none">'
                . '<path d="M38 22 L66 50 L38 78 L10 50 Z" stroke="currentColor" stroke-width="9"/>'
                . '<path d="M62 22 L90 50 L62 78 L34 50 Z" stroke="currentColor" stroke-width="9"/>'
                . '</svg>';
        case 'TPB':
            return '<svg class="card-watermark" viewBox="0 0 100 100" fill="none">'
                . '<polygon points="50,82 16,24 84,24" stroke="currentColor" stroke-width="11" stroke-linejoin="round"/>'
                . '</svg>';
        default:
            return '<svg class="card-watermark" viewBox="0 0 100 100" fill="none">'
                . '<circle cx="65" cy="65" r="36" stroke="currentColor" stroke-width="10"/>'
                . '<circle cx="65" cy="65" r="18" stroke="currentColor" stroke-width="6"/>'
                . '</svg>';
    }
}

/**
 * Màu nhận diện thương hiệu chuẩn của từng ngân hàng (dùng cho thanh tỷ trọng số dư & chấm legend).
 */
function bankBrandColor(string $code): string
{
    $map = [
        'VCB'     => '#006B3F',
        'MOMO'    => '#A50064',
        'TCB'     => '#E30613',
        'MB'      => '#003DA5',
        'BIDV'    => '#006B68',
        'ACB'     => '#0046AD',
        'VPB'     => '#008446',
        'TPB'     => '#662D91',
        'VTB'     => '#005993',
        'AGR'     => '#9E1B34',
        'STB'     => '#0054A6',
        'ZALOPAY' => '#0068FF',
    ];
    return $map[strtoupper(trim($code))] ?? '#0284c7';
}
