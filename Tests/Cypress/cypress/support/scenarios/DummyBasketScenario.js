import Session from "Actions/utils/Session";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import RegisterAction from "Actions/storefront/account/RegisterAction";
import LoginAction from "Actions/storefront/account/LoginAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";


const session = new Session();

const topMenu = new TopMenuAction();
const register = new RegisterAction();
const login = new LoginAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();


export default class DummyBasketScenario {

    /**
     *
     * @param quantity
     * @param firstname
     * @param lastname
     */
    constructor(quantity, firstname, lastname) {
        this.quantity = quantity;
        this.firstname = firstname;
        this.lastname = lastname;
    }

    /**
     *
     */
    execute() {

        const user_email = "dev@localhost.de";
        const user_pwd = "MollieMollie111";

        cy.visit('/');

        register.doRegister(user_email, user_pwd, this.firstname, this.lastname);

        session.resetSession();

        login.doLogin(user_email, user_pwd);

        topMenu.clickOnFirstCategory();

        listing.clickOnFirstProduct();

        pdp.addToCart(this.quantity);

        // wait 1 second
        // it stuck 1 time
        cy.wait(1000);

        checkout.goToCheckoutInOffCanvas();
    }

}
