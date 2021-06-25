export default class NavigationRepository {

    /**
     *
     * @returns {*}
     */
    getFirstCategoryItem() {
        return cy.get('.navigation--list-wrapper > .navigation--list > :nth-child(2) > .navigation--link');
    }

}
