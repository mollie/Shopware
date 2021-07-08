var path = require('path')

module.exports = {
   resolve: {
       alias: {
           Actions: path.resolve(__dirname, 'cypress/support/actions'),
           Repositories: path.resolve(__dirname, 'cypress/support/repositories'),
           Scenarios: path.resolve(__dirname, 'cypress/support/scenarios'),
           Services: path.resolve(__dirname, 'cypress/support/services'),
           Fixtures: path.resolve(__dirname, 'cypress/fixtures'),
           Plugins: path.resolve(__dirname, 'cypress/plugins')
       }
   }
}
