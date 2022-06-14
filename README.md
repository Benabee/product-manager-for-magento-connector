# Product Manager Manager for Magento Connector

This Magento 2 extension must be used with
[Product Manager for Magento](https://www.benabee.com/en/product-manager-for-magento).

It allows the software to connect to the websit and access the website data.



## Installation


Upload the archive to the Magento root folder and run these commands:
```
tar xvzf product-manager-connector-0.4.tar.gz app/code/Benabee/ProductManagerConnector/
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

Close Product Manager for Magento if it's running.

On Windows, open configV2.xml in ```C:\Users\{Your username}\AppData\Local\Benabee\ProductManagerForMagento```.

On Mac, open configV2.xml in ```~/Library/Application Support/Product Manager for Magento```.

![](doc/configuration1.png)

Replace the bridge filename by "productmanagerconnector/".

Replace the encryptionKey by the generated Security Key.

Key is not used anymore. You can delete the value.

![](doc/configuration2.png)

Save the file.


Version history
===============
- Version 0.2 based on bridge version 2.1.8
- Version 0.3 based on bridge version 2.4.0




