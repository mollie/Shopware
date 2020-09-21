<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

class ApplePayDomainFileDownloader
{

    /**
     * this is a static file for all merchants of mollie
     */
    const URL_FILE = 'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association';

    /**
     * this is the required local file name
     * that has to be within the doc root of the merchant shop
     */
    const LOCAL_FILENAME = 'apple-developer-merchantid-domain-association';


    /**
     * @param $docRoot
     * @return mixed|void
     */
    public function downloadDomainAssociationFile($docRoot)
    {
        $content = file_get_contents(self::URL_FILE);

        $appleFolder = $docRoot . '/.well-known';

        if (!file_exists($appleFolder)) {
            mkdir($appleFolder);
        }

        file_put_contents($appleFolder . '/' . self::LOCAL_FILENAME, $content);
    }

}
