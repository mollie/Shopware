
## Easy Testing

Running Cypress tests is easy!
Just install it by using the built-in makefile commands
and either open your tests in the Cypress UI, or run them directly from your CLI.


### Installation

This folder contains a `makefile` with all required commands.
Run the installation command to install Cypress and all its dependencies on your machine

```ruby 
make install
```


### Cypress UI
If you want to run your Cypress UI, just open it with the following command.
Please note, because this is an Open Source project, we cannot include a
shop URL in the configuration. Thus you need to provide it on your own.

Some tests might differ across Shopware versions, so you can provide your version number as well.
The default will be the latest supported Shopware version.

```ruby 
# open for latest version
make open-ui url=http://localhost

# open for Shopware 5.6.9
make open-ui shopware=5.6.9 url=http://localhost
```

### Run in CLI
You can also use the CLI command to run Cypress on your machine or directly
in your build pipeline.
Please provide the Shopware version you are testing against as well as 
the URL of your shop.

```ruby 
make run shopware=5.6.9 url=http://localhost
```



### Tags
You can run a subsegment of tests by providing tags when running Cypress.
These tags need to exist in the title of a test.
We recommend the prefix @, su like '@core'.

```ruby 
make run shopware=5.x url=https://my-local-or-remote-domain tags='@core @smoke'
```

Here is a list of currently allowed tags.
Please use them if appropriate.


| Tag   | Description |
|-------| --- |
| @core | Indicates that the test does not require a Mollie API key. These tests will also run in the PR pipeline before something is merged. |


### TestRail Integration
This Cypress project integrates with our TestRail project.
TestRail is a software to manage test cases keep track on their statuses.

You could, in theory, configure your own TestRail credentials (cypress.env.json), but unfortunately the mapped test cases
will not match your IDs, so it will probably not work. This is only for our QA team at the moment.
