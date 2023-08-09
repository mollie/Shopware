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
     * @throws \Exception
     * @return void
     */
    public function downloadDomainAssociationFile($docRoot)
    {
        $appleFolder = $docRoot . '.well-known';

        if (!file_exists($appleFolder)) {
            mkdir($appleFolder);
        }

        $localFileName = $appleFolder . '/' . self::LOCAL_FILENAME;

        $fileHandle = fopen($localFileName, 'w');


        set_time_limit(0);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::URL_FILE);
        curl_setopt($ch, CURLOPT_FILE, $fileHandle);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception('Error when downloading Apple Pay domain association file.');
        }

        curl_close($ch);
        fclose($fileHandle);
    }
}
