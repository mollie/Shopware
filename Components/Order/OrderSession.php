<?php

namespace MollieShopware\Components\Order;

use ArrayObject;
use Enlight_Components_Session_Namespace;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\Compatibility\LegacyStructConverter;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\Repository;
use Shopware_Controllers_Frontend_Checkout;

class OrderSession
{

    /**
     * @var LegacyStructConverter
     */
    private $legacyStructConverter;

    /**
     * @var Repository
     */
    private $repoPayments;

    /**
     *
     * @var Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @param LegacyStructConverter $legacyStructConverter
     * @param Enlight_Components_Session_Namespace $session
     */
    public function __construct(LegacyStructConverter $legacyStructConverter, Enlight_Components_Session_Namespace $session)
    {
        $this->legacyStructConverter = $legacyStructConverter;
        $this->session = $session;

        $this->repoPayments = Shopware()->Container()->get('models')->getRepository(Payment::class);
    }

    /**
     * This function allows you to set a custom address object
     * before creating the actual order in the session.
     *
     * @param Shopware_Controllers_Frontend_Checkout $checkoutController
     * @param OrderAddress $address
     */
    public function setCustomerData(Shopware_Controllers_Frontend_Checkout $checkoutController, OrderAddress $address)
    {
        $userData = $checkoutController->View()->getAssign('sUserData');

        $userData['billingaddress']['firstname'] = $address->getFirstname();
        $userData['billingaddress']['lastname'] = $address->getLastname();
        $userData['billingaddress']['street'] = $address->getStreet();
        $userData['billingaddress']['zipcode'] = $address->getZipcode();
        $userData['billingaddress']['city'] = $address->getCity();
        $userData['billingaddress']['countryId'] = $address->getCountry()['id'];
        $userData['billingaddress']['country'] = $address->getCountry();

        $userData['shippingaddress']['firstname'] = $address->getFirstname();
        $userData['shippingaddress']['lastname'] = $address->getLastname();
        $userData['shippingaddress']['street'] = $address->getStreet();
        $userData['shippingaddress']['zipcode'] = $address->getZipcode();
        $userData['shippingaddress']['city'] = $address->getCity();
        $userData['shippingaddress']['countryId'] = $address->getCountry()['id'];
        $userData['shippingaddress']['country'] = $address->getCountry();

        $checkoutController->View()->assign('sUserData', $userData);
    }

    /**
     * @param Shopware_Controllers_Frontend_Checkout $checkoutController
     * @param Payment $paymentMethod
     * @param ShopContext $shopContext
     */
    public function prepareOrderSession(Shopware_Controllers_Frontend_Checkout $checkoutController, Payment $payment, ShopContext $shopContext)
    {
        # convert our shopware payment
        # to a storefront payment method, which allows us to use the
        # legacy struct converter, because we need that ARRAY in the end ;)
        /** @var Payment $paymentMethod */
        $paymentMethod = $this->repoPayments->find($payment->getId());

        # older shopware versions do not have
        # a boolean argument here, so let's check the argument count
        $r = new \ReflectionMethod(Shopware_Controllers_Frontend_Checkout::class, 'getBasket');
        if (count($r->getParameters()) > 0) {
            $basket = $checkoutController->getBasket(false);
        } else {
            $basket = $checkoutController->getBasket();
        }

        # the main order variables is the basket, yes
        $sOrderVariables = $basket;

        # ...however inside our order variables
        # there are sub array that are also the basket...aehm..yes... :)
        $sOrderVariables['sBasketView'] = $basket;
        $sOrderVariables['sBasket'] = $basket;

        # make sure our user the data is being
        # correctly added from our previously
        # created guest user
        $sOrderVariables['sUserData'] = $checkoutController->View()->getAssign('sUserData');

        # make sure we always use our payment method for the order we create
        $sOrderVariables['sUserData'] ['additional']['user']['paymentID'] = $paymentMethod->getId();
        $sOrderVariables['sUserData'] ['additional']['payment'] = $this->mockPaymentLegacyStructConverter($paymentMethod, $shopContext);

        # finish our variables (shopware default)
        $sOrderVariables = new ArrayObject($sOrderVariables, ArrayObject::ARRAY_AS_PROPS);

        # add the prepared order to our session
        $this->session->offsetSet('sOrderVariables', $sOrderVariables);
    }

    /**
     * We use our own, because older shopware versions do not
     * have this at the moment.
     *
     * @param Payment $paymentMethod
     * @param ShopContext $shopContext
     * @return array
     */
    private function mockPaymentLegacyStructConverter(Payment $paymentMethod, ShopContext $shopContext)
    {
        $rc = new \ReflectionClass(LegacyStructConverter::class);

        if ($rc->hasMethod('convertPaymentStruct')) {

            # load our payments gateway to get storefront payment methods
            $gatewayPayments = Shopware()->Container()->get("shopware_storefront.payment_gateway");
            $storefrontPaymentMethods = $gatewayPayments->getList([$paymentMethod->getId()], $shopContext);
            $paymentMethod = $storefrontPaymentMethods[$paymentMethod->getId()];

            # convert a storerfront payment method using our legacy struct converter
            return $this->legacyStructConverter->convertPaymentStruct($paymentMethod);
        }

        return [
            'id' => $paymentMethod->getId(),
            'name' => $paymentMethod->getName(),
            'description' => $paymentMethod->getDescription(),
            'template' => $paymentMethod->getTemplate(),
            'class' => $paymentMethod->getClass(),
            'table' => $paymentMethod->getTable(),
            'hide' => $paymentMethod->getHide(),
            'additionaldescription' => $paymentMethod->getAdditionalDescription(),
            'debit_percent' => $paymentMethod->getDebitPercent(),
            'surcharge' => $paymentMethod->getSurcharge(),
            'surchargestring' => $paymentMethod->getSurchargeString(),
            'position' => $paymentMethod->getPosition(),
            'active' => $paymentMethod->getActive(),
            'esdactive' => $paymentMethod->getEsdActive(),
            'embediframe' => $paymentMethod->getEmbediframe(),
            'hideprospect' => $paymentMethod->getHideProspect(),
            'action' => $paymentMethod->getAction(),
            'pluginID' => $paymentMethod->getPluginID(),
            'source' => $paymentMethod->getSource(),
            'mobile_inactive' => $paymentMethod->getMobileInactive(),
        ];
    }
}
