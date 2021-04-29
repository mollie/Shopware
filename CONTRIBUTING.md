BE A PART OF IT!
=================

Giving the world something back is great!

If you would like to contribute and do something to improve this project, we'd be more than happy about it!

Here are some short guides on how to get started and what to consider when working on this project.



## Development Environment



### 1. Shopware Version

Please install the plugin in your Shopware 5 environment. Take care of the supported minimum version of this plugin.

It would be perfect to develop and test it on the latest version as well as on the minimum version.



### 2. Configure Domain

In order to be able to create payments with Mollie, you would need a domain that is NOT `localhost`.

The easiest way to do this, is to create a custom domain locally using the `/etc/hosts` file. 

Open your `etc/hosts` file and append the line below with a custom domain that you would like to use:

```bash
127.0.0.1 your-domain-xyz.com
```

Now all your requests to this domain will be redirected to your localhost and passed on to the correct Shopware shop that matches your domain.



> This needs to be done only once!



### 3. Configure Shopware

Now that you have created your custom domain, please use this one within your shop configuration in Shopware.

If this has been done, you should be able to start the shop with the provided custom domain.



## Development



### 1. Installation

The plugin is delivered with all production dependencies.

So you can just install it in the Shopware Backend and provide your Mollie Account API keys. Afterwards you should already be able to create your first payment.

If you want to dig deeper into development, you might need some `DEVELOPER TOOLS`. 
You can easily install them using this command from the makefile located in the root directory of the plugin.

```bash
make dev
```



### 2. Local Webhooks

You should already be able to develop new features. 

If you want the full experience, including webhooks locally on your machine, please see the corresponding guide on the WIKI page:
https://github.com/mollie/Shopware/wiki/Dev-Webhooks

I promise - this is so much more fun when creating new features!



> Keep in mind, it's not always necessary!



### 3. Code Architecture
You might think that the architecture is a bit "wild". That is in fact the truth.
The plugin has been grown through the help of quite a lot of people.
We now try to improve the overall quality step by step.

You might now ask where to place your files and how to design your changes?
Please read this as a small guide:

#### Components
If you have a functionality that is a centralized way to archieve a certain goal, then
it might be a component. 
I't important that this functionality should imply any custom behaviour of the plugin itself.
Think about separations of concerns and pay attention if the functionality is really part of 
the plugin or if it could be used in any other software too (if designed correctly).

#### Gateways
We try to use gateways for a centralized communication to external services, such as the Mollie API.
This is not yet done obviously, but it's the goal.

#### Services
If your functionality is wrapped in a central place, but has nothing to do with the plugin itself, please place it in the services section.

"Nothing to do" means, that it's designed independently of the Mollie plugin and that it could be placed in any other software out there.


### 4. Code Style

We do have a code style that we need for this project.

To make it easy for you to use these standards, there are some make commands that you should use:

```bash

## Check for PHP Syntax errors
make phpcheck

## Check for PHP version and minimum compatibility
make phpmin 

## Starts the PHP CS Fixer (no-auto fixing -> please use PR command)
make csfix

## Starts the PHPStan Analyzer
make stan
```



### 5. Code Quality

No must-have, but always happy about improvements! 
Use the built-in `PHPMetrics Analyzer` to dig deeper into complexity, dependencies, coupling, violations and more.

```bash
make metrics
```



This command will create a new report HTML file that you can just open and use!



## Testing

Testing is a must-have for us!

We have provided 2 tools for you.

There's a setup for `PHPUnit Tests` as well as a very easy `Cypress E2E Test Suite` that you can just run locally.



### PHPUnit

Please use this command to run your PHPUnit tests. 

It is configured to include Code Coverage reports, so there will be a new report HTML file that you can open and use to improve your testing coverage.

```bash
make test
```



What are our requirements for PHP Unit Tests?

* Function Description:
  Sometimes tests might be a bit hard to understand, so we require an easy human-readable description what is really going on here!
* Testing Structure:
  Please avoid having every line set next to each other! Your test tells a story - please make sure a developer can easily understand that one.
  Use paragraphs or whatever is necessary to have a really beautiful and easy to understand testing code.
* Fakes or Mocks:
  We'd be happy if you already design your code with interfaces, so you can easily create real fake objects for your tests.
  If that's not possible, please use at least Mocks or Stubs for your tests.


### Cypress E2E

If you open the `Tests` folder and navigate to the `Cypress` directory, you will find another `makefile`. 

This is your main makefile for everything related to Cypress. It helps you to get started as easy as possible.

```bash
# Install Cypress first
make install

# Open Cypress UI to easily view and create tests
make open-ui url=https://your-domain-xxx.com

# Automatically run all E2E tests in your terminal
make run url=https://your-domain-xxx.com
```



Creating Cypress tests is easy!
But please stick with the used `Keyword-Driven` Design Pattern with Actions and Object Repositories!
We try to avoid selectors and unstable click-routes directly within a test!



## Pull Requests



### Prepare Pull Request

Our Github repository includes a pipeline that should test everything that could go wrong!
To make this process a bit easier for you, we've created a separate command that prepares the whole code and checks it.

```bash
make pr
```



This command will not only run the analyzers and unit tests, but also start `PHP CS Fixer` in Auto-Fixing mode. 
Please keep in mind to use this wisely and verify the changes made by this tool!

We also highly recommend to test your changes in the latest Shopware 5 version as well as in the minimum supported version. 
At least we'd be happy about it :)

Afterwards please run this command to only commit production dependencies.
It's a plug and play plugin, and thus they need to exist!

```bash
make install
```



### PR Checklist

Before you create your Pull Request, here's a short check list for you:

* Tested locally?! (also with Shopware minimum Version?)
* Unit Tests created where appropriate?
* "make pr" command passes?
* Installed production dependencies (no DEV!)



### Create Pull Request

If everything passed, push your changes to your fork and create a Pull Request on Github. 

Let us know `WHY` you need these changes and `WHAT` you actually did!

If everything seems fine, we'd be happy to merge your changes and add it to an upcoming and official release!



THANKS FOR BEING A PART OF THIS!