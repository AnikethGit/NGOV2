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
        // Coordinates measured pixel-precisely from the 3503×2299 template image.
        // Scale: 0.0848 mm/px (X)  0.0913 mm/px (Y).
        // Font: Arial Bold 11pt, black — clearly readable over the template.

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);        // solid black — maximum readability

        // ── Left column ───────────────────────────────────────────────────────
        // Receipt No. — dotted line sits at y≈99mm, above the QR code (y≈105mm).
        $pdf->SetXY(47, 99);
        $pdf->Cell(50, 5, $receiptNo);

        // ── Right column (x ≥ 91 mm — clear of QR code) ──────────────────────
        // Date  (right side, after "Date :" label which ends ~y=93mm)
        $pdf->SetXY(237, 93);
        $pdf->Cell(50, 5, $date);

        // Amount in words — spans up to two printed dotted lines.
        //   Line 1: space to the right of "Received with thanks a sum of Rupees in words"
        //   Line 2: full right-column width before the printed "Only /-"
        [$wordsLine1, $wordsLine2] = self::splitWords($words, 38);

        $pdf->SetXY(164, 107);
        $pdf->Cell(122, 5, $wordsLine1);

        if ($wordsLine2 !== '') {
            $pdf->SetXY(91, 116);
            $pdf->Cell(170, 5, $wordsLine2);
        }

        // From Sri/Smt  (label ends ~x=113mm, row y=126mm)
        $pdf->SetXY(113, 126);
        $pdf->Cell(172, 5, $name);

        // Address  (label ends ~x=110mm, row y=138mm)
        $pdf->SetXY(110, 138);
        $pdf->Cell(175, 5, $address);

        // Aadhar / PAN No.  (label ends ~x=130mm, row y=146mm)
        $pdf->SetXY(130, 146);
        $pdf->Cell(52, 5, $pan);

        // Mobile No.  (label ends ~x=220mm, same row y=146mm)
        $pdf->SetXY(220, 146);
        $pdf->Cell(65, 5, $mobile);

        // Towards  (label ends ~x=115mm, row y=157mm)
        $pdf->SetXY(115, 157);
        $pdf->Cell(68, 5, $cause);

        // By Cash / UPI / QR value  (label ends ~x=222mm, same row y=157mm)
        $pdf->SetXY(222, 157);
        $pdf->Cell(65, 5, $payMode);

        // ── Rs. box (x=18–125mm, y=155–193mm) ───────────────────────────────
        // "Rs." printed text ends at x≈71mm; value starts after it.
        $pdf->SetXY(75, 175);
        $pdf->Cell(48, 5, $amountStr);

        return $pdf->Output('S');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Split amount-in-words string so the first chunk fits in $limit characters.
     * Returns [line1, line2].
     */
    private static function splitWords(string $words, int $limit): array
    {
        if (strlen($words) <= $limit) {
            return [$words, ''];
        }
        $parts = explode(' ', $words);
        $line1 = '';
        $line2 = '';
        foreach ($parts as $word) {
            $test = $line1 === '' ? $word : $line1 . ' ' . $word;
            if (strlen($test) <= $limit) {
                $line1 = $test;
            } else {
                $line2 .= ($line2 === '' ? '' : ' ') . $word;
            }
        }
        return [$line1, $line2];
    }

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
