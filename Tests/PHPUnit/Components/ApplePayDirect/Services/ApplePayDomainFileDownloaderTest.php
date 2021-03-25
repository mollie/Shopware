<?php

namespace MollieShopware\Tests\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader;
use PHPUnit\Framework\TestCase;

class ApplePayDomainFileDownloaderTest extends TestCase
{

    /**
     * This test verifies that our URL download file
     * isn't touched without recognizing it.
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader
     */
    public function testUrlFile()
    {
        $this->assertEquals('https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association', ApplePayDomainFileDownloader::URL_FILE);
    }

    /**
     * This test verifies that our local filename for
     * the verification file isn't touched without recognizing it.
     *
     * @covers \MollieShopware\Components\ApplePayDirect\Services\ApplePayDomainFileDownloader
     */
    public function testLocalFile()
    {
        $this->assertEquals('apple-developer-merchantid-domain-association', ApplePayDomainFileDownloader::LOCAL_FILENAME);
    }
}
