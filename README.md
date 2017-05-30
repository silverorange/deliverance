Deliverance
===========
Mailing list management tools. Provides a factory for mailing list providers,
user interface for mailing list sign up, and admin tools for list management.

Although Deliverance is written to be provider agnostic, MailChimp is the
only supported provider.

Provides the following data objects:

 - DeliveranceCampaignSegment
 - DeliveranceListInterest
 - DeliveranceNewsletter
 - DeliveranceNewsletterTemplate

Installation
------------
Make sure the silverorange composer repository is added to the `composer.json`
for the project and then run:

```sh
composer require silverorange/deliverance
```
