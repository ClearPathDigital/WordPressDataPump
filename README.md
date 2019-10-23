WordpressdataPump
=================

A PHP data pump for importing blog posts from another system into Wordpress via the the XML-RPC WordPress API.

This class requires the Wordpress [XMLRPC Client by Hieu Le](https://github.com/letrunghieu/wordpress-xmlrpc-client) as an interface.  It's easy to include it if you are using [Composer](https://getcomposer.org/).

Progress to here is merely a working class that abstracts away some of the nitty-gritty of preparing and importing data from another system into Wordrpress.  We have used this data pump to import hundreds of blogs posts in varying formats into Wordpress.

## Dependencies
* [XMLRPC Client by Hieu Le](https://github.com/letrunghieu/wordpress-xmlrpc-client)

## Setup
### Composer
You will need to use [Composer](https://getcomposer.org/) to add the above-mentioned dependenices to your project.  Simply include (or create) the following in a `composer.json` file in the root of your project folder and run `composer update` to get set up.

	{
			"repositories": [
				{
					"type": "vcs",
					"url": "https://github.com/ClearPathDigital/WordPressDataPump"
				}
			],
			"require": {
					"clearpathdigital/wordpressdatapump":">=0.0.1"
			}
	}

## Usage Example

	$endpoint = 'https://www.yourwpsite.com/xmlrpc.php';
	$username = 'username';
	$password = 'password';

	$WPDP = new \ClearPathDigital\WordpressDataPump\WordpressDataPump($endpoint, $username, $password);

	$WPDP->setDate('Today');
	$WPDP->setTitle('WordPressDataPump Test Post');
	$WPDP->setContent('This is a test post created by WordPressDataPump.');
	$WPDP->addTerm('category', 'Testing');
	$WPDP->addTerm('post_tag', 'TestTag1');
	$WPDP->addTerm('post_tag', 'TestTag2');
	$WPDP->addMedia('path/to/picture.jpeg', 'WordpressDataPump Test Image', 'This is a caption for a test image uploaded by WordpressDataPump.');
	$WPDP->createPost();
