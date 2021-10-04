<?php

namespace MollieShopware\Tests\Models\Mails;

use MollieShopware\Models\Mails\BankTransferMailData;
use PHPUnit\Framework\TestCase;

class BankTransferMailDataTest extends TestCase
{

    /**
     * This test verifies that the structure for the email template is correct.
     * This structure is used by merchants to build there email templates
     * and must not be changed, once deployed
     */
    public function testMailArray()
    {
        $mailData = new BankTransferMailData(true, 'Mollie', 'NL123', 'BIC123', 'REF-123');

        $expected = [
            'exists' => true,
            'name' => 'Mollie',
            'account' => 'NL123',
            'bic' => 'BIC123',
            'reference' => 'REF-123',
            'formatted' => [
                'html' => "Mollie<br />NL123<br />BIC123<br />REF-123",
                'text' => "Mollie\r\nNL123\r\nBIC123\r\nREF-123",
            ]
        ];

        $this->assertSame($expected, $mailData->toArray());
    }

}