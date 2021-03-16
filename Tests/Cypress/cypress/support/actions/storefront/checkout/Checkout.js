import OffCanvasRepository from 'Repositories/storefront/checkout/OffCanvasRepository';
import CartConfirmRepository from 'Repositories/storefront/checkout/CartConfirmRepository';

class Checkout {

    /**
     *
     */
    continueToCheckout() {

        const repo = new OffCanvasRepository();

        repo.getCheckoutButton().click();
    }

    /**
     *
     */
    confirmSwitchPayment() {

        const repo = new CartConfirmRepository();

        repo.getSwitchPaymentMethodsButton().click();
    }

}

export default Checkout;
