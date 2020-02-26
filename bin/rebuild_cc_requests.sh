#!/bin/bash
grep -i -e x_Card_Num -e x_Login /home/my/logs/billingd.log* | \
  sed -e s#"' => '"#'":"'#g -e s#"',  '"#'","'#g \
  -e s#"' => "#'":'#g -e s#",  '"#',"'#g \
  -e s#"] CC Request: array (  '"#'","'#g \
  -e s#"^.*billingd.log.*\["#'{"date":"'#g \
  -e s#"',) (\(.*\))$"#'","lid":"\1"}'#g \
  -e s#"] array (  'x_Login"#'","x_Login'#g \
  -e s#"\\\*'"#"'"#g \
  -e s#"	"#" "#g \
  > cc_charge_requests_raw.json;
