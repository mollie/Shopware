<?php

// Mollie Shopware Plugin Version: 1.4.7

namespace _PhpScoper5cd2cac49fa56;

/*
 * NOTE: The examples are using a text file as a database.
 * Please use a real database like MySQL in production code.
 */
function database_read($orderId)
{
    $orderId = \intval($orderId);
    $database = \dirname(__FILE__) . "/database/order-{$orderId}.txt";
    $status = @\file_get_contents($database);
    return $status ? $status : "unknown order";
}
function database_write($orderId, $status)
{
    $orderId = \intval($orderId);
    $database = \dirname(__FILE__) . "/database/order-{$orderId}.txt";
    \file_put_contents($database, $status);
}
