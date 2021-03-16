// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'
import Tags from "./services/Tags";


beforeEach(() => {
    const test = Cypress.mocha.getRunner().suite.ctx.currentTest;
    // verify if we have tags
    // and need to filter for them
    const tags = new Tags();
    tags.verifyTest(test);
});
