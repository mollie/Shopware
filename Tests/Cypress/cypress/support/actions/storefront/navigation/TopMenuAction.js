import NavigationRepository from 'Repositories/storefront/navigation/NavigationRepository';



export default class TopMenuAction {

    /**
     *
     */
    clickOnFirstCategory() {

        const repo = new NavigationRepository();

        repo.getFirstCategoryItem().click();
    }

}


