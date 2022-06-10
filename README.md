# Product Manager Manager for Magento Connector

This Magento 2 extension must be used with
[Product Manager for Magento](https://www.benabee.com/en/product-manager-for-magento).

It allows the software to connect to the websit and access the website data.



## Installation


Upload the archive to the Magento root folder and run these commands:
```
tar xvzf product-manager-connector-0.3.tar.gz
php bin/magento module:enable Benabee_ProductManagerConnector
php bin/magento setup:upgrade
```


## Configuration

To configure the extension, open the admin panel and go to ```Stores``` > ```Configuration``` > ```Catalog``` > ```Product Manager for Magento Connector```.

Click Generate new security key.

Click Save Config.

Copy the security key.

Enter the security key in Product Manager for Magento.


Version history
===============
- Version 0.2 based on bridge version 2.1.8
- Version 0.3 based on bridge version 2.4.0




