#!/bin/bash
# == Konfiguration ==
SERVER=user@meinSSH-Server.de
PASSWORT=GeheimesPasswort
#Der Upload-Pfad auf dem Webserver
PFAD=/www/
OutFile=redirect.html
# Für .htaccess
COND="RewriteCond %{HTTP_HOST} ^ddnsname\.Mein\-Domain\.de$"

# == Konfiguration ENDE==

if [ -e ip.txt ]
 then
   OLD_IP=$(cat ip.txt)
 else
   CUR_IP=$(wget -qO - http://www.icanhazip.com)
   echo $CUR_IP > ip.txt
   OLD_IP=
fi
CUR_IP=$(wget -qO - http://www.icanhazip.com)
if [ "$OLD_IP" == "$CUR_IP" ]
 then
  echo "Kein Update erforderlich."
 else
  echo "Update ist erforderlich."
  # .htaccess erzeugen
  # echo "RewriteEngine On" > .htaccess
  # echo $COND >> .htaccess
  # echo "RewriteRule ^ http://$CUR_IP%{REQUEST_URI} [L,QSA,R=301]" >> .htaccess
  ## Alternative mit Proxy, funktioniert nicht bei jedem Provider
  ## echo "RewriteRule ^ http://$CUR_IP%{REQUEST_URI} [P]" >> .htaccess

  # aktuelle IP-Adresse in redirect.html speichern
  echo "<html>" > $OutFile
  echo "<head>" >> $OutFile
  echo '<meta http-equiv="Refresh" content="1; URL=http://'$CUR_IP'">' >> $OutFile
  echo "</head>" >> $OutFile
  echo "<body>" >> $OutFile
  echo "</body>" >> $OutFile
  echo "</html>" >> $OutFile

  # Dateien per SSH übertragen
  # sshpass mit
  # sudo apt install sshpass
  # installieren
  #sshpass -p "$PASSWORT" scp -v .htaccess "$SERVER:$PFAD"
  sshpass -p "$PASSWORT" scp -v $OutFile "$SERVER:$PFAD"
fi

