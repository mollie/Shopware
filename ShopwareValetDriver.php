<?php

	// Mollie Shopware Plugin Version: 1.3.10

class ShopwareValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return bool
     */
    public function serves($sitePath, $siteName, $uri)
    {
         if (file_exists($sitePath.'/shopware.php')) {
             return true;
         }

        return false;
    }

    /**
     * Determine if the incoming request is for a static file.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string|false
     */
    public function isStaticFile($sitePath, $siteName, $uri)
    {

        if (file_exists($staticFilePath = $sitePath.'/'.$uri) && !is_dir($sitePath . '/' . $uri)) {
            return $staticFilePath;
        }


        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {



        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
        $url .= $_SERVER['HTTP_HOST'] . $uri;


        if (file_exists($staticFilePath = $sitePath . $uri . '/index.php')){
            $_SERVER['SCRIPT_NAME'] = $url . '/index.php';
            return $staticFilePath;
        }


        $_SERVER['SCRIPT_NAME'] = $url;
        return $sitePath.'/shopware.php';

    }
}
