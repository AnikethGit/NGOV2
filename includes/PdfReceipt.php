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
        // Dark blue for most fields — matches the printed blue text on the template.
        // All coordinates are in mm from top-left corner of the A4 landscape page.
        // Fine-tune X/Y here if print alignment drifts after real-world testing.

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 139);      // #00008B dark blue

        // ── Left column ───────────────────────────────────────────────────────
        // Receipt No. — sits on its own dotted line, ABOVE the QR code.
        // QR code starts at ~y=105mm; the "Receipt No." dotted line is at ~y=100mm.
        $pdf->SetXY(47, 100);
        $pdf->Cell(40, 5, $receiptNo);

        // ── Right column (x > 96 mm — clear of the QR code) ──────────────────
        // Date  (far right, after "Date :")
        $pdf->SetXY(264, 92);
        $pdf->Cell(20, 5, $date);

        // Amount in words — spans up to two dotted lines.
        // Line 1: narrow space to the right of the long printed label (~40 chars).
        // Line 2: full right-column width before "Only /-".
        [$wordsLine1, $wordsLine2] = self::splitWords($words, 40);

        $pdf->SetXY(164, 107);              // after "Received with thanks a sum of Rupees in words"
        $pdf->Cell(122, 5, $wordsLine1);

        if ($wordsLine2 !== '') {
            $pdf->SetXY(96, 115);           // second dotted line, full right-column width
            $pdf->Cell(165, 5, $wordsLine2);
        }

        // From Sri/Smt
        $pdf->SetXY(132, 123);
        $pdf->Cell(153, 5, $name);

        // Address
        $pdf->SetXY(118, 131);
        $pdf->Cell(167, 5, $address);

        // Aadhar / PAN No.
        $pdf->SetXY(132, 139);
        $pdf->Cell(52, 5, $pan);

        // Mobile No.
        $pdf->SetXY(212, 139);
        $pdf->Cell(73, 5, $mobile);

        // Towards
        $pdf->SetXY(118, 147);
        $pdf->Cell(64, 5, $cause);

        // By Cash / UPI / QR  (payment method value)
        $pdf->SetXY(212, 147);
        $pdf->Cell(73, 5, $payMode);

        // ── Rs. box (bottom-left) ─────────────────────────────────────────────
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(30, 178);
        $pdf->Cell(70, 5, $amountStr);

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
