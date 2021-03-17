import PDPRepository from 'Repositories/storefront/products/PDPRepository';

class PDP {

    /**
     *
     */
    addToCart() {

        const repo = new PDPRepository();

        repo.getAddToCartButton().click();
    }

}

export default PDP;
