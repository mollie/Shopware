import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';


const devices = new Devices();
const session = new Session();

const register = new RegisterAction();
const login = new LoginAction();

const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


describe('iDEAL Issuers', () => {

    before(function () {
        devices.setDevice(device);
        register.doRegister(user_email, user_pwd);
    })

    beforeEach(() => {
        session.resetBrowserSession();
    });


    it('Ajax Route working for signed in users', () => {

        cy.visit('/');
        login.doLogin(user_email, user_pwd);

        // we start the request
        // and make sure that we have a 200 status code
        // and at least 1 found issuer.
        cy.request({
            url: '/Mollie/idealIssuers',
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.data.length).to.be.at.least(1);

        })
    })


    it('Ajax Route blocked for anonymous users', () => {

        // we start the request and make sure
        // that we get a 500 error
        cy.request({
            url: '/Mollie/idealIssuers',
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(500);
        })

    })

})
