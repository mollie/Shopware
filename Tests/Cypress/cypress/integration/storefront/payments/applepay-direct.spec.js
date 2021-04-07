describe('Apple Pay Direct', () => {

    it('Domain Verification file has been downloaded', () => {

        cy.request('/.well-known/apple-developer-merchantid-domain-association');

    })

})
