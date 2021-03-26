<?php

namespace MollieShopware\Components\BasketSnapshot;

class BasketSnapshot
{

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     *
     */
    const SESSION_KEY = 'mollieBasketSnapshot';


    /**
     * BasketSnapshot constructor.
     *
     * @param \Enlight_Components_Session_Namespace $session
     */
    public function __construct(\Enlight_Components_Session_Namespace $session)
    {
        $this->session = $session;
    }

    /**
     * @param \sBasket $basket
     * @throws \Enlight_Exception
     */
    public function createSnapshot(\sBasket $basket)
    {
        if ($this->hasSnapshot()) {
            return;
        }

        $snapshot = [];

        foreach ($basket->sGetBasketData()['content'] as $lineItem) {
            $snapshot[] = [
                'ordernumber' => $lineItem['ordernumber'],
                'quantity' => $lineItem['quantity'],
            ];
        }

        $this->session->offsetSet(self::SESSION_KEY, $snapshot);
    }

    /**
     * @return bool
     */
    public function hasSnapshot()
    {
        $snapshot = $this->session->offsetGet(self::SESSION_KEY);

        return ($snapshot !== null);
    }

    /**
     * @param \sBasket $basket
     * @throws \Enlight_Event_Exception
     * @throws \Enlight_Exception
     * @throws \Zend_Db_Adapter_Exception
     */
    public function restoreSnapshot(\sBasket $basket)
    {
        if (!$this->hasSnapshot()) {
            return;
        }

        $basket->sDeleteBasket();

        /** @var array $snapshot */
        $snapshot = $this->session->get(self::SESSION_KEY);

        foreach ($snapshot as $lineItem) {
            $ordernumber = $lineItem['ordernumber'];
            $qty = $lineItem['quantity'];

            $basket->sAddArticle($ordernumber, $qty);
        }

        $this->session->offsetSet(self::SESSION_KEY, null);
    }
}
