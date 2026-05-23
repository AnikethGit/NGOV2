<?php
/**
 * PdfReceipt — generates A4 landscape donation receipt PDF.
 *
 * Uses the physical receipt template as a full-page background image and
 * overlays the dynamic donor/donation fields at their exact print positions.
 *
 * Requires FPDF v1.82 at includes/fpdf/fpdf.php
 * Download: http://www.fpdf.org
 */

require_once __DIR__ . '/fpdf/fpdf.php';

class PdfReceipt
{
    /**
     * Generate a PDF receipt and return the binary string.
     *
     * @param  array $d  Merged donation + user row (see ReceiptService::dispatch)
     * @return string    Raw PDF binary
     */
    public static function generate(array $d): string
    {
        $pdf = new FPDF('L', 'mm', 'A4');   // Landscape, mm units, A4 (297×210 mm)
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(false);

        // ── Background template ────────────────────────────────────────────────
        $tpl = __DIR__ . '/../images/receipt-template.png';
        if (file_exists($tpl)) {
            $pdf->Image($tpl, 0, 0, 297, 210);
        }

        // ── Prepare field values ───────────────────────────────────────────────
        $receiptNo = $d['receipt_number'] ?? ('SDSMBT-' . date('Ymd') . '-' . str_pad($d['id'] ?? 0, 6, '0', STR_PAD_LEFT));

        $ts   = $d['updated_at'] ?? $d['created_at'] ?? null;
        $date = $ts ? date('d-m-Y', strtotime($ts)) : date('d-m-Y');

        $amount    = (float)($d['amount'] ?? 0);
        $words     = self::amountInWords($amount);
        $amountStr = number_format($amount, 2);

        $name    = $d['donor_name'] ?? trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
        $address = $d['donor_address'] ?? '';
        $pan     = $d['donor_pan']     ?? $d['pan_number'] ?? '';
        $mobile  = $d['donor_phone']   ?? $d['phone']      ?? '';
        $cause   = ucwords(str_replace('-', ' ', $d['cause'] ?? ''));

        $gateway = strtolower($d['payment_gateway'] ?? '');
        $payMode = ($gateway === 'razorpay') ? 'Online: Razorpay' : ($d['payment_method'] ?? 'Online');

        // ── Text overlay ───────────────────────────────────────────────────────
        // All X/Y positions are in mm, derived from pixel coordinates on the
        // 3503×2299 template image (scale 0.08478 mm/px X, 0.09134 mm/px Y).
        //
        // ┌─────────────────────────────────────────────────────────────────┐
        // │  FINE-TUNE: change $yOff to shift ALL fields up (negative) or  │
        // │  down (positive) in mm without touching individual coordinates. │
        // └─────────────────────────────────────────────────────────────────┘
        $yOff = -11.0;   // ← adjust this one value to move everything up/down

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);    // solid black

        // Receipt No.  px(524, 1165)
        $pdf->SetXY(44.43, 106.42 + $yOff);
        $pdf->Cell(100, 5, $receiptNo);

        // Date  px(2919, 1066)
        $pdf->SetXY(247.49, 97.37 + $yOff);
        $pdf->Cell(46, 5, $date);

        // Amount in words — two dotted lines.
        //   Line 1 starts at px(2094,1232), max end px(3277,1232) → 100.30 mm wide.
        //   Line 2 starts at px(885, 1353) if overflow.
        $line1MaxW = 100.30;
        $parts     = explode(' ', $words);
        $line1 = '';
        $line2 = '';
        foreach ($parts as $word) {
            $test = $line1 === '' ? $word : $line1 . ' ' . $word;
            if ($pdf->GetStringWidth($test) <= $line1MaxW) {
                $line1 = $test;
            } else {
                $line2 .= ($line2 === '' ? '' : ' ') . $word;
            }
        }
        $pdf->SetXY(177.54, 112.54 + $yOff);
        $pdf->Cell(100.30, 5, $line1);
        if ($line2 !== '') {
            $pdf->SetXY(75.03, 123.59 + $yOff);
            $pdf->Cell(185, 5, $line2);
        }

        // From Sri/Smt  px(1226, 1477)
        $pdf->SetXY(103.95, 134.92 + $yOff);
        $pdf->Cell(181, 5, $name);

        // Address  px(1102, 1594)
        $pdf->SetXY(93.43, 145.60 + $yOff);
        $pdf->Cell(191, 5, $address);

        // Aadhar / PAN No.  px(1268, 1711)
        $pdf->SetXY(107.51, 156.29 + $yOff);
        $pdf->Cell(95, 5, $pan);

        // Mobile No.  px(2501, 1711)
        $pdf->SetXY(212.05, 156.29 + $yOff);
        $pdf->Cell(80, 5, $mobile);

        // Towards / Cause  px(1094, 1828)
        $pdf->SetXY(92.75, 166.98 + $yOff);
        $pdf->Cell(140, 5, $cause);

        // Payment method  px(2873, 1828)
        $pdf->SetXY(243.59, 166.98 + $yOff);
        $pdf->Cell(50, 5, $payMode);

        // Rs. amount  px(935, 2203)  — not shifted; box position is fixed
        $pdf->SetXY(79.27, 201.23);
        $pdf->Cell(60, 5, $amountStr);

        return $pdf->Output('S');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Convert a rupee amount to Indian-English words.
     * e.g. 5100.00 → "Five Thousand One Hundred Rupees"
     */
    public static function amountInWords(float $amount): string
    {
        $n = (int)round($amount);
        if ($n === 0) return 'Zero Rupees';
        return trim(self::numberToWords($n)) . ' Rupees';
    }

    private static function numberToWords(int $n): string
    {
        if ($n === 0) return '';

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $result = '';

        if ($n >= 10000000) {
            $result .= self::numberToWords((int)($n / 10000000)) . ' Crore ';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $result .= self::numberToWords((int)($n / 100000)) . ' Lakh ';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $result .= self::numberToWords((int)($n / 1000)) . ' Thousand ';
            $n %= 1000;
        }
        if ($n >= 100) {
            $result .= $ones[(int)($n / 100)] . ' Hundred ';
            $n %= 100;
        }
        if ($n >= 20) {
            $result .= $tens[(int)($n / 10)] . ' ';
            $n %= 10;
        }
        if ($n > 0) {
            $result .= $ones[$n] . ' ';
        }

        return trim($result);
    }
}
