<?php

// Mollie Shopware Plugin Version: 1.4.10

namespace _PhpScoper5cd2cac49fa56;

/*
 * Delete a customer from the Mollie API.
 */
try {
    /*
     * Initialize the Mollie API library with your API key or OAuth access token.
     */
    require "../initialize.php";
    $mollie->customers->delete("cst_fE3F6nvX");
    echo "<p>Customer deleted!</p>";
} catch (\Mollie\Api\Exceptions\ApiException $e) {
    echo "API call failed: " . \htmlspecialchars($e->getMessage());
}
