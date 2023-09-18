# Dynamisches DNS im Eigenbau

**Zusatzinformationen:**

Webserver Apache installieren: https://www.pcwelt.de/1085980

Portfreigaben: https://www.pcwelt.de/1146038 und https://www.pcwelt.de/1198563

SSH: So klappt die Fernwartung mit Linux: https://www.pcwelt.de/1153544

## Öffentliche IP-Nummer bekannt machen

Das Das Bash-Script IP-Mailer/IP-Mailer.sh speichert die IP-Nummer in der Datei "ip.txt" und versendet diese per E-Mail. Bei jedem Aufruf prüft das Script, ob sich die IP geändert hat und versendet nur dann eine aktualisierte Nachricht.

IP-Mailer.sh ermittelt auf dem Server-PC zuerst die externe IP-Nummer mit der Zeile
```
CUR_IP=$(wget -qO - http://www.icanhazip.com)
```
Im Script sind Alternativen genannt, wie sich die IP von einer Fritzbox oder über einen eigenen Webserver auslesen lässt (siehe PHP-Script "PHP-Update/ddns/geIP.php").

Der E-Mail-Versand erfolgt über das Tool ssmtp, das Sie über das gleichnamige Paket installieren. Die Konfiguration für Ihren E-Mail-Anbieter erfolgt über Dateien im Ordner "/etc/ssmtp". Beispieldateien für GMX finden Sie im Ordner "etc".

**Alternativen:** Legen Sie die Textdatei mit der IP-Nummer auf einem öffentlich zugänglichen Speicher im Internet ab, beispielsweise Dropbox (Script: "Dropbox/IP-Dropbox.sh"). Auf Ihrem Server muss dazu der Dropbox-Client installiert sein (https://www.dropbox.com/de/install-linux). Ein Aufruf des Scripts mit 
```
IP-Dropbox.sh update 
```
kopiert die Datei "ip.txt" in den Dropbox-Ordner. Es erstellt außerdem die Datei „redirect.html“ (siehe Kommentare im Script). Wenn Sie das Script ohne Parameter aufrufen, lädt es die HTML-Datei herunter und öffnet sie im Browser. Auf diesem Weg gelangen Sie direkt zum Webserver im Heimnetz. Damit das funktioniert müssen Sie im Script den Link zur Datei anpassen. Die Adresse erhalten Sie in der Dropbox-Weboberfläche bei der Datei nach einem Klick auf "Link kopieren". Wenn Sie anderen Nutzern den Link mitteilen, sehen diese den Inhalt der HTML-Datei, müssen die Datei dann herunterladen und im Browser öffnen.

## Externen Webserver verwenden

Das Script "IP-Scp.sh" baut die aktuelle IP-Nummer in die Datei "redirect.html" ein und lädt die Datei auf den Webserver. Damit das ohne Passworteingabe läuft, installieren Sie zusätzlich das Tool sshpass über das gleichnamige Paket. Im Script konfigurieren Sie hinter "SERVER=" Benutzername und Domain und hinter "PASSWORT=" das Passwort für den SSH-Zugriff auf Ihrem Server. Hinter "PFAD=" geben Sie den Ordner an, in dem Sie die Datei auf dem Server speichern wollen. Nach dem Upload lässt sich die Weiterleitung in jedem Browser über http://MeinServer.de/redirect.html aufrufen.

**Weiterleitung per Apache-Direktive:** Apache lässt sich über die Datei .htaccess konfigurieren, die man beispielsweise für die Um- oder Weiterleitung von Webseiten verwenden kann. Das Script IP-Scp.sh kann auch eine .htaccess-Datei erzeugen. Passen Sie die Konfiguration an und entfernen Sie die Kommentarzeichen im zugehörigen Abschnitt.

## Einen eigenen DNS-Server konfigurieren

Für die vollwertige Nachbildung eines Dienstes für dynamisches DNS benötigt man einen Root-Server im Internet, auf dem man die nötige Software für Linux installieren kann.

Der Standard sieht vor, dass mindestens zwei DNS-Server für eine Domain zuständig sind. Der Grund dafür: Eine Domain muss sich über DNS immer auflösen lassen, auch wenn der zugehörige Server gerade nicht läuft. Für die Konfiguration benötigt Ihr Server daher zwei IPv4-Adressen, was allerdings nicht für Ausfallsicherheit sorgt. Die Bedingung lässt sich technisch korrekt nur über einen Secondary Nameserver erreichen, der in einem anderen Netzwerk läuft. Teilweise bieten die Webhoster diesen Dienst an, wenn nicht kann man auf das kostenlose Buddydns (www.buddyns.com) zurückgreifen.

Einen DNS-Server installiert man unter Ubuntu oder Linux Mint mit
```
sudo apt install bind9
```
Die Konfiguration ist danach unter "/etc/bind" zu finden. In der Hauptdatei "/etc/named.conf" ändern Sie in der Regel nichts, eigene Anpassungen erfolgen in der Datei "/etc/named.conf.local". Wir verwenden im Folgenden die Domäne „beispiel.de“, die Sie jeweils durch den Domainnamen Ihres Servers ersetzen. Die Beispieldateien liegen im Ordner "etc/bind".

**Schritt 1:** Erstellen Sie die Datei „/etc/bind/db.beispiel.de“. In dieser Zonendatei werden die Domäne, die Subdomänen und die zugehörigen IP-Adressen konfiguriert. Passen Sie in der Datei Domainangaben und IP-Nummern an. Sobald der DNS-Server öffentlich verfügbar ist, erhöhen Sie nach jeder Änderung die Seriennummer im Bereich „SOA“ um 1. Sonst werden die Änderungen nicht aktiv.

**Schritt 2:** Prüfen Sie die Konfiguration im Terminal mit
```
named-checkzone beispiel.de db.beispiel.de
```
Werden keine Fehler ausgegeben, tragen Sie in die Datei "/etc/named.conf.local" die Zeile
```
zone "beispiel.de" {type master; file "/etc/bind/db.beispiel.de";serial-update-method unixtime;};
```
ein. Danach starten Sie Bind über
```
sudo systemctl restart bind9
```
neu.

**Schritt 3:** Ändern Sie beim Webhoster die Nameserver für Ihre Domain. Tragen Sie "ns1.beispiel.de" und "ns2.beispiel.de" mit den zugehörigen IP-Nummern ein. Es kann bis zu 24 Stunden dauern, bis die Änderungen im DNS ankommen. Mit 
```
dig beispiel.de NS
```
lassen sich die konfigurierten Nameserver prüfen.

**Schritt 4:** Für die dynamischen Domains benötigen Sie die Datei "/etc/bind/ddns.beispiel.de" und zusätzlich einen Schlüssel, über den sich der Server zuhause identifiziert. Mit dem Script "confgen_beispiel.de.sh" erzeugen Sie die Datei "ddns.beispiel.de.keys", die Sie in die "/etc/bind/named.conf.local" mit
```
include "/etc/bind/ddns.beispiel.de.keys"
```
einbinden. 

Die ebenfalls erzeugte Datei "key.[Subdomain].ddns.beispiel.de" kopieren Sie in Ihr Home-Verzeichnis auf dem heimischen Server. Außerdem installieren Sie das Paket "bind9-dnsutils", in dem das Tool nsupdate enthalten ist. Am gleichen Ort speichern Sie auch das Script "update_dyndns.sh". Es ermittelt die externe IP des Routers, vergleicht sie mit dem Ergebnis der Abfrage Ihres DNS-Servers und aktualisiert die IP bei Bedarf mit nsupdate.

## DNS-Server über PHP-Script aktualisieren

Fast alle DSL-Router enthalten eine Update-Funktion für dynamische DNS-Dienste. Der Vorteil im Vergleich zu einem Script, das auf dem heimischen Server läuft: Der Router "weiß" wenn sich die IP-Adresse ändert und teilt das dann dem DNS-Server mit, den Sie mit bind konfiguriert haben. Das PHP-Script "PHP-Updater/ddns/update.php" stammt von https://linux.robert-scheck.de/netzwerk/eigener-dyndns-dienst. Auf der Webseite finden Sie die Dokumentation zur Konfiguration. Kopieren Sie die Beispieldateien auf Ihren root-Server im Rechenzentrum, auf dem das Script dann über https://MeinServer.de/ddns/update.php erreichbar ist. Für den Webserver muss PHP installiert sein.

Das Script "PHP-Updater/mk_users.conf.sh" dient dazu, das verschlüsselte Passwort in der Datei "users.conf" zu speichern.

In der Datei "hosts.conf" konfigurieren Sie den Hostnamen für dynamisches DNS. In "keys.conf" sind die zugehörigen Authentifisierungsschlüssel untergebracht.

**Router konfigurieren**: Bei einer Fritzbox beispielsweise öffnen Sie im Browser die Konfigurationsoberfläche über die Adresse http://fritz.box. Gehen Sie auf "Internet -> Freigaben" und auf die Registerkarte „DynDNS“. Setzen Sie ein Häkchen vor "DynDNS benutzen" und geben Sie die Daten für den Server ein. Unter "Update-URL" tragen Sie folgende Zeile ein:
```
https://meinserver.de/ddns/update.php?hostname=<domain>&myip=<ipaddr>
```
Statt "meinserver.de" verwenden Sie die URL, über die Ihr Webspace erreichbar ist. Unter "Domainname" setzen Sie diese URL ebenfalls ein. Bei "Benutzername" und "Kennwort" geben Sie die Daten ein, die in Sie beim Erstellen von "users.conf" verwendet haben. Klicken Sie zum Abschluss auf "Übernehmen". 

Die Fritzbox ruft jetzt die angegebene URL auf und überträgt dabei die aktuelle IP-Adresse aus der Variablen „<ipaddr> und das Passwort aus der Variablen „<pass>“. Nach einer Zwangstrennung startet die Fritzbox das Script automatisch. 

Das Script "PHP-Updater/ddns/getIP.PHP" liefert die öffteliche IP-Nummer des aufrufenden Gerätes zurück, wenn Sie auf Ihrem Webserver
```
https://meinserver.de/ddns/getIP.php
```
abfragen. 

Sie können dieses Script unabhängig von "update.php" in den oben genannten Scripten zur Ermittlung der öffentlichen IP-Adresse verwenden und sind dann nicht auf einen externen Server angewiesen.





