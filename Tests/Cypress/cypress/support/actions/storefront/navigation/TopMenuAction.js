import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';

class TopMenuAction {

    /**
     *
     */
    clickOnClothing() {

        const repo = new NavigationRepository();

        repo.getClothingMenuItem().click();
    }

}

export default TopMenuAction;
