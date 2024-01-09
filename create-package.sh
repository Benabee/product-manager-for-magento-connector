
VERSION=1.2.2
FILE=product-manager-connector-$VERSION.tar.gz

rm $FILE
tar -cvz -f $FILE Block Controller doc etc Helper Model view composer.json README.md LICENSE.txt registration.php
