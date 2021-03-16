import TagExpressionParser from '@cucumber/tag-expressions';

class Tags {

    
    /**
     * 
     * @param test
     */
    verifyTest(test) {

        let envTags = Cypress.env('tags');
        
        // we don't have any tags
        // then leave the test as it is
        if (!envTags) {
            return;
        }
        
        const tagger = new TagExpressionParser(envTags);
        this.verifyTags(tagger, test);
    }

    /**
     * 
     * @param tagger
     * @param test
     */
    verifyTags(tagger, test) {

        const runTest = tagger.evaluate(test.fullTitle());

        // we start with our lowest level
        // then we check our parent suites and groups,
        // if we then get found-tag in an upper level
        // we remove the pending again
        test.pending = !runTest;

        // immediately return
        // if we have a tag, then we don't need
        // to ask the higher levels
        if (!test.pending) {
            return;
        }

        if (test.parent !== undefined) {
            this.verifyTags(tagger, test.parent)
        }
    }

}

export default Tags;


