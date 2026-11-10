<?php

declare(strict_types=1);

namespace App\Payslip\Pdf;

use TCPDF;

/**
 * TCPDF without the built-in header/footer and the hidden "Powered by TCPDF" link.
 */
final class PayslipDocument extends TCPDF
{
    public function __construct()
    {
        parent::__construct('L', 'mm', 'A5', true, 'UTF-8', false);

        // The parent constructor forces this on; it prints a hidden link on the last page.
        $this->tcpdflink = false;

        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
    }
}
