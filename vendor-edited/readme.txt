These plugins have been edited to work with shopware (which uses outdated versions of some php libraries).

GuzzleHTTP
----------------------

> shopware uses version 5.3.2
> mollie client uses version 6.3.2 (and has a changed interface).

We took guzzle out of the vendor folder and renamed its namespace to GuzzleHttpV6:

composer update
php vendor-edited/replace.php GuzzleHttp GuzzleHttpV6


