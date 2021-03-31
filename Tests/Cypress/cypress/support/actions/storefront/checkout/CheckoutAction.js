import OffCanvasRepository from 'Repositories/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/storefront/checkout/ConfirmRepository';
import PaymentsRepository from 'Repositories/storefront/checkout/PaymentsRepository';


const repoOffCanvas = new OffCanvasRepository();
const repoConfirm = new ConfirmRepository();
const repoPayments = new PaymentsRepository();


class CheckoutAction {

    /**
     *
     */
    goToCheckoutInOffCanvas() {
        repoOffCanvas.getCheckoutButton().click();
    }

    /**
     *
     */
    openPaymentSelectionOnConfirm() {
        repoConfirm.getSwitchPaymentMethodsButton().click();
    }

    /**
     *
     * @param paymentName
     */
    switchPaymentMethod(paymentName) {

        repoConfirm.getSwitchPaymentMethodsButton().click();

        // click on the name of the payment
        cy.contains(paymentName).click();

        // attention, there is a modal popup appearing
        // so its not immediately available, lets just wait
        // until our payment method has been successfully selected
        cy.wait(3000);
        repoPayments.getSubmitButton().click();
    }

    /**
     *
     */
    placeOrderOnConfirm() {
        repoConfirm.getTerms().check();
        repoConfirm.getSubmitButton().click();
    }

}

export default CheckoutAction;
