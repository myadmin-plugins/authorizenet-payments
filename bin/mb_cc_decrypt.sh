#!/bin/bash

# settings go here
AGENT="User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2790.0 Safari/537.36";
ACCEPT="Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
ACCEPT_ENC="Accept-Encoding: gzip, deflate, br";
ACCEPT_LANG="Accept-Language: en-US,en;q=0.8";
CONTTYPE="Content-Type: application/x-www-form-urlencoded";
MYUSER=mbjoe;
MYPASS=xxxxxxxxxxx;
PIN="6969";
MYIP="$(curl -s https://my.interserver.net/myip.php)";
DOMAIN="interserver.net";
WPATH="/cms";

# help content here
if [ "$#" -lt 1 ]; then
	echo "ModernBill CC Decryptor v1.0 (c) 2016 InterServer, Inc. coded by Joe Huss <detain@interserver.net>"
	echo " Not Enough Arguments!  Correct Syntax is: $0 <client id>"
	#echo "IP: ${MYIP} PIN: ${PIN} USER: ${MYUSER}"
	exit;
fi;
# login
CLIENTID="$1";
URLD="https://${DOMAIN}";
URLP="https://${DOMAIN}${WPATH}";
PHPSESS="$(curl "${URLP}/index.php" -H 'Pragma: no-cache' -H "Origin: ${URLD}" \
 -H "${ACCEPT_ENC}" -H "${ACCEPT_LANG}" -H 'Upgrade-Insecure-Requests: 1' -H "${AGENT}" -H "${CONTTYPE}" -H "${ACCEPT}" \
 -H 'Cache-Control: no-cache' -H "Referer: ${URLP}/" -H 'Connection: keep-alive' \
 --data "op=login&submit=submit&username=${MYUSER}&password=${MYPASS}&specbut1.x=40&specbut1.y=8" --compressed -s -c cookie-jar | grep PHPSESSID= | cut -d= -f2 | cut -d\; -f1)";
CC="$(curl "${URLP}/admin.php?op=view_cc&db_table=client_info" -H 'Pragma: no-cache' -H "Origin: ${URLD}" \
 -H "${ACCEPT_ENC}" -H "${ACCEPT_LANG}" -H 'Upgrade-Insecure-Requests: 1' -H "${AGENT}" -H "${CONTTYPE}" -H "${ACCEPT}" \
 -H 'Cache-Control: no-cache' -H "Referer: ${URLP}/admin.php?op=view_cc&db_table=client_info&tile=client&id=client_id|${CLIENTID}" -H 'Connection: keep-alive' \
 -H 'Connection: keep-alive' \
 --data "id=client_id%7C${CLIENTID}&_s=1&lek_pin=${PIN}&password=${MYPASS}" --compressed -s -b cookie-jar -c cookie-jar |grep -A2 "CC Number" | tail -n "1" | cut -d\< -f2  | cut -d\> -f2)";
/bin/rm -f cookie-jar
echo "${CC}"
