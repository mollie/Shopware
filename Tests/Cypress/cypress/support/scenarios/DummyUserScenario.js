import Session from "Actions/utils/Session";
import RegisterAction from "Actions/storefront/account/RegisterAction";
import LoginAction from "Actions/storefront/account/LoginAction";


const session = new Session();
const register = new RegisterAction();
const login = new LoginAction();


export default class DummyUserScenario {

    /**
     *
     */
    execute() {

        const user_email = "dev@localhost.de";
        const user_pwd = "MollieMollie111";

        cy.visit('/');

        register.doRegister(user_email, user_pwd, 'Mollie', 'Mollie');

        session.resetSession();

        login.doLogin(user_email, user_pwd);
    }

}
