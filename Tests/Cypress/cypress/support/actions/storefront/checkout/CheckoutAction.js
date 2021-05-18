import OffCanvasRepository from 'Repositories/storefront/checkout/OffCanvasRepository';
import ConfirmRepository from 'Repositories/storefront/checkout/ConfirmRepository';
import PaymentsAction from "Actions/storefront/checkout/PaymentsAction";


const paymentsAction = new PaymentsAction();
const repoOffCanvas = new OffCanvasRepository();
const repoConfirm = new ConfirmRepository();


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
        paymentsAction.selectPayment(paymentName);

        // attention, there is a modal popup appearing
        // so its not immediately available, lets just wait
        // until our payment method has been successfully selected
        cy.wait(3000);
        paymentsAction.submitPage();
    }

    /**
     *
     * @returns {*}
     */
    getTotalFromConfirm() {
        return repoConfirm.getTotalSum().invoke('text').then((total) => {

            total = total.replace("€", "");

            return total;
        });
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
