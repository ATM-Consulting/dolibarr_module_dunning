Dunning Management
==================

A module for managing dunning in Dolibarr.

Licence
-------

GPLv3 or (at your option) any later version.

See COPYING for more information.

INSTALL
-------

- Make sure Dolibarr (v >= 3.4) is already installed and configured on your server.

- In your Dolibarr installation directory, edit the htdocs/conf/conf.php file

- Find the following lines:

		//$=dolibarr_main_url_root_alt ...
		//$=dolibarr_main_document_root_alt ...

- Uncomment these lines (delete the leading "//") and assign a sensible value according to your Dolibarr installation

	For example :

	- UNIX:

			$dolibarr_main_url_root = 'http://localhost/Dolibarr/htdocs';
			$dolibarr_main_document_root = '/var/www/Dolibarr/htdocs';
			$dolibarr_main_url_root_alt = 'http://localhost/Dolibarr/htdocs/custom';
			$dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';

	- Windows:

			$dolibarr_main_url_root = 'http://localhost/Dolibarr/htdocs';
			$dolibarr_main_document_root = 'C:/My Web Sites/Dolibarr/htdocs';
			$dolibarr_main_url_root_alt = 'http://localhost/Dolibarr/htdocs/custom';
			$dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';

	For more information about the conf.php file take a look at the conf.php.example file.

	*Note that in the upcoming Dolibarr 3.5, the $dolibarr\_main\_url\_root\_alt will become a relative path*

- Clone the repository in $dolibarr\_main\_document\_root\_alt/dunning

	*(You may have to create the custom directory first if it doesn't exist yet.)*

	```
	git clone --recursive git@github.com:rdoursenaud/dolibarr-module-template.git dunning
	```

	**The module now uses a git submodule to fetch the PHP Markdown library.**

	If your git version is less than 1.6.5, the --recursive parameter won't work.

	Please use this instead to fetch the latest version:

		git clone git@github.com:rdoursenaud/dolibarr-module-template.git dunning
		cd dunning
		git submodule update --init

- From your browser:

	- log in as a Dolibarr administrator

	- go to "Setup" -> "Modules"

	- the module is under the other modules tab

	- you should now be able to enable the new module

Other Licences
--------------

Uses [Michel Fortin's PHP Markdown](http://michelf.ca/projets/php-markdown/) Licensed under BSD to display this README in the module's about page.
