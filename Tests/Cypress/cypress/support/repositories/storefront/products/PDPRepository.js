class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        return cy.get('.buybox--button');
    }

    /**
     *
     * @returns {*}
     */
    getQuantity()
    {
        return cy.get('#sQuantity');
    }

}

export default PDPRepository;
