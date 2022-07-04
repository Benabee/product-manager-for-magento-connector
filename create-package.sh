
VERSION=1.0.0
FILE=product-manager-connector-$VERSION

rm $FILE.tar.gz
gtar -cvz --exclude .DS_Store --exclude *.sh --exclude *.tar.gz --exclude *.zip -f $FILE.tar.gz . 
tar -ztvf $FILE.tar.gz

rm $FILE.zip
zip -r $FILE.zip . -x .DS_Store  -x *.sh -x *.tar.gz -x *.zip

