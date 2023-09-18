<?php
 /*
  *  ddns-server 0.1.0
  *
  *  (c) 2013-2014 by Robert Scheck <ddns-server@robert-scheck.de>
  *
  *  This program is free software; you can redistribute it and/or modify
  *  it under the terms of the GNU General Public License as published by
  *  the Free Software Foundation; either version 2 of the License, or
  *  (at your option) any later version.
  *
  *  This program is distributed in the hope that it will be useful,
  *  but WITHOUT ANY WARRANTY; without even the implied warranty of
  *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  *  GNU General Public License for more details.
  *
  *  You should have received a copy of the GNU General Public License
  *  along with this program; if not, write to the Free Software
  *  Foundation, Inc.,
  *  59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
  */

  // Output according to DynDNS and No-IP DDNS Update API
  function ddns_api($code, $headers = array())
  {
    if(is_array($headers))
      foreach($headers as $header)
        header($header);
    else
      header($headers);

    echo (is_array($code)) ? implode("\n", $code) : $code;
    exit;
  }

  // Check if given string is a valid IPv4 address
  function is_ipv4($ipv4)
  {
    $num = "(25[0-5]|2[0-4]\d|[01]?\d\d|\d)";

    if(preg_match("/^($num)\\.($num)\\.($num)\\.($num)$/", $ipv4))
      return TRUE;
    else
      return FALSE;
  }

  // Check if given string is a valid IPv6 address
  function is_ipv6($ipv6)
  {
    $pattern1 = "([A-Fa-f0-9]{0,4}:){7}[A-Fa-f0-9]{0,4}";
    $pattern2 = "[A-Fa-f0-9]{0,4}::([A-Fa-f0-9]{0,4}:){0,5}[A-Fa-f0-9]{0,4}";
    $pattern3 = "([A-Fa-f0-9]{0,4}:){2}:([A-Fa-f0-9]{0,4}:){0,4}[A-Fa-f0-9]{0,4}";
    $pattern4 = "([A-Fa-f0-9]{0,4}:){3}:([A-Fa-f0-9]{0,4}:){0,3}[A-Fa-f0-9]{0,4}";
    $pattern5 = "([A-Fa-f0-9]{0,4}:){4}:([A-Fa-f0-9]{0,4}:){0,2}[A-Fa-f0-9]{0,4}";
    $pattern6 = "([A-Fa-f0-9]{0,4}:){5}:([A-Fa-f0-9]{0,4}:){0,1}[A-Fa-f0-9]{0,4}";
    $pattern7 = "([A-Fa-f0-9]{0,4}:){6}:[A-Fa-f0-9]{0,4}";

    if(preg_match("/^($pattern1)$|^($pattern2)$|^($pattern3)$|^($pattern4)$|^($pattern5)$|^($pattern6)$|^($pattern7)$/", $ipv6))
      return TRUE;
    else
      return FALSE;
  }

  $usermap = $hostmap = $keyneed = $keymap = $cache = $hostnames = $nsupdates = array();

  // Read available users and passwords into a usermap
  $lines = @file("users.conf");

  if(is_array($lines))
    foreach($lines as $line)
    {
      if(preg_match("/^#/", $line))
        continue;

      list($user, $password) = explode(":", trim($line), 2);
      $usermap[$user] = $password;
    }
  else
    ddns_api("911");

  // Read available hostnames and related users into a hostmap
  $lines = @file("hosts.conf");

  if(is_array($lines))
    foreach($lines as $line)
    {
      if(preg_match("/^#/", $line))
        continue;

      list($host, $ttl, $zone, $server, $key, $users) = explode(":", trim($line), 6);
      $hostmap[$host]['ttl'] = (empty($ttl) ? 60 : $ttl);
      $hostmap[$host]['zone'] = $zone;
      $hostmap[$host]['server'] = $server;
      if(!empty($key))
      {
        $hostmap[$host]['key'] = $key;
        $keyneed[] = $host;
      }
      $hostmap[$host]['users'] = explode(",", $users);
    }
  else
    ddns_api("911");

  // Read available keys with algorithm into a keymap
  $lines = @file("keys.conf");

  if(is_array($lines))
    foreach($lines as $line)
    {
      if(preg_match("/^#/", $line))
        continue;

      list($name, $hmac, $secret) = explode(":", trim($line), 3);
      $keymap[$name]['hmac'] = (empty($hmac) ? "hmac-md5" : $hmac);
      $keymap[$name]['key'] = $secret;
    }
  else
    if(count($keyneed) > 0)
      ddns_api("911");

  // Read available cached hostnames and IP addresses
  $lines = @file("cache.db");

  if(is_array($lines))
    foreach($lines as $line)
    {
      list($host, $ip) = explode(":", trim($line), 2);
      $cache[$host] = $ip;
    }
  else
    ddns_api("911");

  // Only HTTP methods GET and POST are allowed
  if(empty($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], array("GET", "POST")))
    ddns_api("badagent", "HTTP/1.1 405 Method Not Allowed");

  // Request HTTP basic access authentication if needed
  if(empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']))
    ddns_api("badauth", array("WWW-Authenticate: Basic realm=\"DynDNS API Access\"", "HTTP/1.1 401 Unauthorized"));

  // Authenticate using crypt(3) against user map
  if(empty($usermap[$_SERVER['PHP_AUTH_USER']]) || crypt($_SERVER['PHP_AUTH_PW'], $usermap[$_SERVER['PHP_AUTH_USER']]) !== $usermap[$_SERVER['PHP_AUTH_USER']])
    ddns_api("badauth", "HTTP/1.1 403 Forbidden");

  // Get IPv4 or IPv6 address, fallback to remote address
  if(!empty($_REQUEST['myip']) && (is_ipv4($_REQUEST['myip']) || is_ipv6($_REQUEST['myip'])))
    $myip = $_REQUEST['myip'];
  else
    $myip = $_SERVER['REMOTE_ADDR'];

  // Get hostname(s) to be processed
  if(!empty($_REQUEST['hostname']))
  {
    foreach(explode(",", strtolower($_REQUEST['hostname'])) as $hostname)
    {
      // Check if hostname is a valid FQDN
      if(!preg_match("/^[a-zA-Z0-9.-]+$/", $hostname))
        $hostnames[$hostname] = "notfqdn";

      // Check if user is allowed to update the hostname
      elseif(empty($hostmap[$hostname]['users']) || !in_array($_SERVER['PHP_AUTH_USER'], $hostmap[$hostname]['users']))
        $hostnames[$hostname] = "nohost";

      // Check if IP address is already cached
      elseif(!empty($cache[$hostname]) && $cache[$hostname] === $myip)
        $hostnames[$hostname] = "nochg $myip";

      // Fill list of hostname(s) to be updated
      else
      {
        $hostnames[$hostname] = "";
        $nsupdates[] = $hostname;
      }
    }
  }
  else
    ddns_api("notfqdn");

  // Process hostname(s) to be updated
  if(count($nsupdates) > 0)
  {
    $result = -1;
    $process = proc_open("nsupdate -v", array(0 => array("pipe", "r"), 1 => array("file", "/dev/null", "w"), 2 => array("file", "/dev/null", "w")), $pipes);

    if(is_resource($process))
    {

      foreach($nsupdates as $hostname)
      {
        fwrite($pipes[0], "server " . $hostmap[$hostname]['server'] . "\n");
        if(!empty($hostmap[$hostname]['key']) && !empty($keymap[$hostmap[$hostname]['key']]['key']))
          fwrite($pipes[0], "key " . $keymap[$hostmap[$hostname]['key']]['hmac'] . ":" . $hostmap[$hostname]['key'] . " " . $keymap[$hostmap[$hostname]['key']]['key'] . "\n");

        fwrite($pipes[0], "zone " . $hostmap[$hostname]['zone'] . ".\n");
        fwrite($pipes[0], "update delete $hostname. A\n");
        fwrite($pipes[0], "update delete $hostname. AAAA\n");
        fwrite($pipes[0], "update add $hostname. " . $hostmap[$hostname]['ttl'] . " " . (is_ipv4($myip) ? "A" : "AAAA") . " $myip\n");
        fwrite($pipes[0], "send\n");
      }

      fclose($pipes[0]);

      $result = proc_close($process);
    }

    // Mark processed hostname(s) as such
    foreach($nsupdates as $hostname)
    {

      if($result === 0)
      {
        $hostnames[$hostname] = "good $myip";
        $cache[$hostname] = $myip;
      }
      else
      {
        $hostnames[$hostname] = "dnserr";
        unset($cache[$hostname]);
      }
    }

    // Write cache file or mark host(s) as failed
    if(!array_walk($cache, create_function('&$val, $key', '$val = "$key:$val";')) || file_put_contents("cache.db", join("\n", $cache)) === FALSE)
      foreach($nsupdates as $hostname)
        $hostnames[$hostname] = "dnserr";
  }

  // Cause final output
  ddns_api(array_values($hostnames));
?>
