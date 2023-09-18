#!/bin/sh

# Dropbox Verzeichnis. Bitte bei Bedarf anpassen
DropBoxFolder=~/Dropbox/dyndns

# Download-URL für Redirect-Datei. Bitte anpassen
DownloadURL="https://www.dropbox.com/X/XXXX/redirect.html?dl=0"

# aktuelle IP-Adresse in redirect.html speichern
OutFile=redirect.html

if [ "$1" = "update" ]; then
 # aktuelle IP-Adresse ermitteln
 CUR_IP=$(wget -qO - http://www.icanhazip.com)
 echo "Externe IP-Adresse: $CUR_IP"
 # aktuelle IP-Adresse in redirect.html speichern
 echo "<html>" > $OutFile
 echo "<head>" >> $OutFile
 echo '<meta http-equiv="Refresh" content="1; URL=http://'$CUR_IP'">' >> $OutFile
 echo "</head>" >> $OutFile
 echo "<body>" >> $OutFile
 echo "</body>" >> $OutFile
 echo "</html>" >> $OutFile
 cp $OutFile $DropBoxFolder/$OutFile
 echo "IP in Datei $DropBoxFolder/$OutFile gespeichert"
else
 wget $DownloadURL -O $OutFile
 firefox $OutFile
fi
 

