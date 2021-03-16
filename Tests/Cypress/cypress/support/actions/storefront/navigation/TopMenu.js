import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';

class TopMenu {

    /**
     *
     */
    clickOnClothing() {

        const repo = new NavigationRepository();

        repo.getClothingMenuItem().click();
    }

}

export default TopMenu;
