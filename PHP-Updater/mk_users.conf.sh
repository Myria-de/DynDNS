#!/bin/bash
username="Benutzer"
# 16_Zeichen_Salt durch 15 zufällige Zeichen ersetzen
export salt=\$6\$16_Zeichen_Salt!\$
# Beispiel mit Passwort Tux+Fisch
cmd=$(php -r 'echo crypt ("Tux+Fisch", getenv("salt"));')
echo $username:$cmd > users.conf
