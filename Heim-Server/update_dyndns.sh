#!/bin/bash

# Script per Cronjob alle 30 Minuten ausführen
# crontab -e
# Zeile hinzufügen
# */30 * * * * /home/[User]/update_dyndns.sh
# 

#### Konfiguration ####
ZONE=ddns.beispiel.de
# gewünschte Subdomain
DOMAIN=luna.ddns.beispiel.de
DNSKEY=$HOME/key.luna.ddns.beispiel.de
NS="ns1.beispiel.de"
CURL=curl
record="A" #IPv4
#### Konfiguration Ende ####
# Die konfigurierte IP über DNS ermitteln
resolved=$(dig +short $record $DOMAIN )
# Möglicherweise stabiler,
# wenn die öffentlichen DNS-Server nicht schnell genug aktualisiert werden.
# IP über den eigenen DSN-Server ermitteln
# resolved=$(dig @ns1.beispiel.de +short $record $DOMAIN )
#
echo "Resolved: $resolved"
# Die aktuelle öffentliche IP des Routers ermitteln
current=$(curl -fsS "https://api.ipify.org")
echo "Current: $current"

# DNS-Server mit nsupdate aktualisieren
[ -n "$current" \
-a "(" "$current" != "$resolved" ")" \
] && {
nsupdate -d -v -k $DNSKEY << EOF
server $NS
zone $ZONE
update delete $DOMAIN A
update add $DOMAIN 60 A $current
send
EOF
echo "Update dyndns: $resolved -> $current"
}
# > /dev/null 2>&1

