<?php

namespace MollieShopware\Components\SessionManager;

use MollieShopware\Models\Transaction;

interface SessionManagerInterface
{

    /**
     * @return string
     */
    public function getSessionId();

    /**
     * @param int $days
     * @return void
     */
    public function extendSessionLifespan($days);

    /**
     * @param Transaction $transaction
     * @return string
     */
    public function generateSessionToken(Transaction $transaction);

    /**
     * @param Transaction $transaction
     * @return void
     */
    public function deleteSessionToken(Transaction $transaction);

    /**
     * @param Transaction $transaction
     * @param string $requestSessionToken
     * @return void
     */
    public function restoreFromToken(Transaction $transaction, $requestSessionToken);
}
