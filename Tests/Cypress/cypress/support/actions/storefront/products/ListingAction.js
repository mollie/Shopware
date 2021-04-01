import ListingRepository from 'Repositories/storefront/products/ListingRepository';

class ListingAction {

    /**
     *
     */
    clickOnFirstProduct() {

        const repo = new ListingRepository();

        repo.getFirstProduct().click();
    }

}

export default ListingAction;
