import ListingRepository from 'Repositories/storefront/products/ListingRepository';


const repo = new ListingRepository();


export default class ListingAction {

    /**
     *
     */
    clickOnFirstProduct() {
        repo.getFirstProduct().click();
    }

    /**
     *
     * @param orderNumber
     */
    clickOnProduct(orderNumber) {
        repo.getProduct(orderNumber).click();
    }

}

