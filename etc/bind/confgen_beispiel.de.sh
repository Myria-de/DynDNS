host=saturn
zone=ddns.beispiel.de
etcdir="/etc/bind"
ddns-confgen -q -k $host.$zone -s $host.$zone. | tee -a $etcdir/$zone.keys > $etcdir/key.$host.$zone
