<?php
// Simple PDF invoice parser using smalot/pdfparser
// Usage: include and call parse_invoice_pdf($filePath)

require_once __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

function parse_invoice_pdf($filePath) {
    $parser = new Parser();
    $pdf = $parser->parseFile($filePath);
    $text = $pdf->getText();

    // Example: extract invoice number, date, total, etc. (customize as needed)
    $invoice = [
        'number' => null,
        'date' => null,
        'total' => null,
        'supplier' => null,
        'raw_text' => $text
    ];

    if (preg_match('/Invoice[\s#:]*([A-Z0-9\-]+)/i', $text, $m)) {
        $invoice['number'] = $m[1];
    }
    if (preg_match('/Date[\s:]*([0-9]{2,4}[\.\/-][0-9]{1,2}[\.\/-][0-9]{1,4})/i', $text, $m)) {
        $invoice['date'] = $m[1];
    }
    if (preg_match('/Total[\s:]*([0-9\.,]+)/i', $text, $m)) {
        $invoice['total'] = $m[1];
    }
    if (preg_match('/Supplier[\s:]*([\p{L}0-9\s]+)/iu', $text, $m)) {
        $invoice['supplier'] = trim($m[1]);
    }
    return $invoice;
}
