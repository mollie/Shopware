import AccountRepository from "Repositories/storefront/account/AccountRepository";

const repoAccount = new AccountRepository();

export default class AccountAction {

    /**
     *
     */
    openPaymentMethods() {

        repoAccount.getSideMenuPaymentMethods().click();
    }

}
