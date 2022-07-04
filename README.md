# Product Manager Manager for Magento Connector

This Magento 2 extension must be used with
[Product Manager for Magento](https://www.benabee.com/en/product-manager-for-magento).

It allows the software to connect to the Magento website and access the website data.



## Installation

The extension can be installed using Composer or an archive file.

### Installation using Composer
```
composer require benabee/product-manager-connector --no-update
```

### Installation using archive file
Upload the archive to the Magento root folder and run these commands:
```
tar xvzf product-manager-connector-1.0.0.tar.gz app/code/Benabee/ProductManagerConnector/
php bin/magento module:enable Benabee_ProductManagerConnector
php bin/magento setup:upgrade
```




## Configuration

To configure the extension, open the admin panel and go to ```Stores``` > ```Configuration``` > ```Catalog``` > ```Product Manager for Magento Connector```.
![](doc/installation1.png)

Click Generate new security key.
![](doc/installation2.png)

Click Save Config.
![](doc/installation3.png)

Copy the security key.

Click File > Configuration Wizard in Product Manager.

Choose Connect with "Product Manager Connector extension (Magento 2 only)".

Paste the secret key.

Complete the Configuration Wizard.



Version history
===============
- Version 0.2 based on bridge version 2.1.8
- Version 0.3 based on bridge version 2.4.0
- Version 1.0.0 based on bridge version 2.4.0



