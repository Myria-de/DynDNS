#!/bin/bash
# == Konfiguration ==
EMAILTO=MeinName@meinedomain.de
EMAILFROM=Mein.Name@gmx.de
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
# Alternativen
# Externe IPv4-Nummer von der Fritzbox im lokalen Netz holen
# CUR_IP=$(curl -s "http://fritz.box:49000/igdupnp/control/WANIPConn1" \
#-H "Content-Type: text/xml; charset="utf-8 \
#-H "SoapAction:urn:schemas-upnp-org:service:WANIPConnection:1#GetExternalIPAddress" \
#-d "<?xml version='1.0' encoding='utf-8'?> \
#<s:Envelope s:encodingStyle='http://schemas.xmlsoap.org/soap/encoding/' \
#xmlns:s='http://schemas.xmlsoap.org/soap/envelope/'> \
#<s:Body> <u:GetExternalIPAddress \
#xmlns:u='urn:schemas-upnp-org:service:WANIPConnection:1' /> \
#</s:Body> </s:Envelope>" \
#| grep -Eo '\<[[:digit:]]{1,3}(\.[[:digit:]]{1,3}){3}\>')
#
##
#
# Externe IP-Nummer vom eigenen Server im Rechenzentrum holen: siehe PHP-Script "PHP-Update/ddns/geIP.php"
# CUR_IP=$(wget -qO- http://meinserver.de/ddns/getIP.php)

if [ "$OLD_IP" == "$CUR_IP" ]
 then
  echo "Kein Update erforderlich."
 else
  echo "Update ist erforderlich."
  #E-Mail IP
  echo "To: $EMAILTO" > ip.mail
  echo "From: $EMAILFROM" >> ip.mail
  echo "Subject: IP-Adresse" >> ip.mail
  echo "" >> ip.mail
  echo "Aktuelle IP ist: $CUR_IP" >> ip.mail
  echo "http://$CUR_IP" >> ip.mail
  ssmtp -v -t < ip.mail
fi

