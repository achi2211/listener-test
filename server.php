<?php

require "Logging.php";

error_reporting(E_ALL);

// Logging class initialization
  $log = new Logging();
 
// set path and name of log file (optional)
  $log->lfile('/tmp/socket-listener.txt');
/**
 * Check dependencies
 */
if( ! extension_loaded('sockets' ) ) {
	  $log->lwrite("This example requires sockets extension (http://www.php.net/manual/en/sockets.installation.php)\n");
	
	exit(-1);
}

if( ! extension_loaded('pcntl' ) ) {
	  $log->lwrite("This example requires PCNTL extension (http://www.php.net/manual/en/pcntl.installation.php)\n");
	exit(-1);
}

//global variables
$port = 54321;
$m_dbh;
 


/**
 * Connection handler
 */
function onConnect( $client ) {


	$event_id = '';
	$cnt = 0;
	$col = array(
	          'box',
	          'transID',
	          'time',
	          'antenna',
	          'hits',
	        );
	$num = array(0,1,2,3,4);

	$pid = pcntl_fork();
	
	if ($pid == -1) {
		 die('could not fork');
	} else if ($pid) {
		// parent process
		return;
	}
	
	$read = '';
	printf( "[%s] Connected at port %d\n", $client->getAddress(), $client->getPort() );
	
	while( true ) {
		$read = $client->read();
		if( $read != '' ) {
			//establish connection with receiver
	        if (substr($read, 0, 1) == 'N') 
	        { 
	            $GLOBALS['log']->lwrite( "event: " . $read . " received\n");

	            echo "event: " . $read . " received\n";

	            $ini = strpos($read, 'EVENT');
	            $end = strlen($read);

	            $event_id = substr($read, $ini + 5, $end);

	            $GLOBALS['log']->lwrite( "Event ID: " . $event_id . "\n");
	            echo "Event ID: " . $event_id . "\n";

	            // $talkback = "S00000"."\r".chr(0); //this worked!

	            $talkback = "S00000\r"; // sent command to receiver
	            $client->send($talkback);
	            $GLOBALS['log']->lwrite( " sent: ". $talkback . "\n");
	            echo " sent: ". $talkback ."\n";
	        }

	         //receive events from the receiver
	        if (substr($read, 0, 1) == 'D') 
	        {
	             $GLOBALS['log']->lwrite( "event data from event ID: " . $event_id . " data: $read \n");

	             echo "event data from event ID: " . $event_id . " data: $read \n";

		     $read = preg_replace('/[^A-Za-z0-9.,-:\-\']/', ' ', $read);
	             $data_exploded = explode(" ", $read);

	            if(COUNT($data_exploded) <= 6)
	            {
		            //regular expresion used to parse data
		            $pattern = "/^(.)(.{6}) (.{8})\.(.{2})(.{2}) 1(.)(.{3})(.{4})/s";

		            if (preg_match($pattern, substr($read, 1, strlen($read)), $pm)) 
		            {
		                $val[0] = $pm[1];                       /* box */
		                $val[1] = $pm[2];                       /* transid */
		                $val[2] = cvTimeDbl($pm[3].".".$pm[4]); /* time.msecs */
		                $val[3] = $pm[5];                       /* pc_id (antenna) */
		                $val[4] = ltrim($pm[7]);                /* lap (hits) */

		                $GLOBALS['log']->lwrite("formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n");

		                echo "formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n";

		                $return_value = AddtoResults($GLOBALS['m_dbh'], $event_id, $col, $val, $num);

		                if ($return_value == 1) 
		                {
		                    $time = time();  
		                       $GLOBALS['log']->lwrite( 'inserted row with transid = ' . $val[1] . "\n");
		                       echo 'inserted row with transid = ' . $val[1] . "\n";
		                }
		            }

		         // this case is used when the receiver has saved scanned chips - all data are sending in one string
		        }else
		        {
		        	$GLOBALS['log']->lwrite('Disconnected data feature'. "\n");
		        	echo 'Disconnected data feature'. "\n";

		        	$index = 0;
		        	$data = '';
				


		        	for ($i = 0; $i < COUNT($data_exploded); $i++) 
		        	{ 
		        		$data .= $data_exploded[$i].' ';

		        		if ($index == 5)
		        		{
		        			$row = substr($data, 0, strlen($data)-1);

 						 $GLOBALS['log']->lwrite( " row: ". $row . "\n");
						 echo " row: ". $row ."\n";


		        			//regular expresion used to parse data
				            $pattern = "/^(.)(.{6}) (.{8})\.(.{2})(.{2}) 1(.)(.{3})(.{4})/s";

				            if (preg_match($pattern, substr($row, 1, strlen($row)), $pm)) 
				            {
				                $val[0] = $pm[1];                       /* box */
				                $val[1] = $pm[2];                       /* transid */
				                $val[2] = cvTimeDbl($pm[3].".".$pm[4]); /* time.msecs */
				                $val[3] = $pm[5];                       /* pc_id (antenna) */
				                $val[4] = ltrim($pm[7]);                /* lap (hits) */

				                $GLOBALS['log']->lwrite("formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n");

				                echo "formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n";

				                $return_value = AddtoResults($GLOBALS['m_dbh'], $event_id, $col, $val, $num);

				                if ($return_value == 1) 
				                {
				                    $time = time();  
				                       $GLOBALS['log']->lwrite( 'inserted row with transid = ' . $val[1] . "\n");
				                       echo 'inserted row with transid = ' . $val[1] . "\n";
				                }
				            }

		        			//inicialize vars
		        			$data  = '';
		        			$index = 0;
		        		}else
		        		{
		        			$index++;
		        		}
		        		
		        	}
		        }

	        }
		}
		else {
			break;
		}
		
		if( preg_replace( '/[^a-z]/', '', $read ) == 'exit' ) {
			break;
		}
		if( $read === null ) {
			printf( "[%s] Disconnected\n", $client->getAddress() );
			return false;
		}
		else {
			printf( "[%s] recieved: %s", $client->getAddress(), $read );
		}
	}
	$client->close();
	printf( "[%s] Disconnected\n", $client->getAddress() );
	
}

function cvtimeDbl($strtime) {
    $tm = explode(".", $strtime);

    if ($tm[0]=='--' || $tm[0]=='') $tm[0] = 0;
        if (substr($tm[0],0,1)=='-') {
            $n = -1;
            $tm[0] = substr($tm[0],1);
    } else $n = 1;
    
    $tms = split(":", $tm[0]);
    
    while (count($tms)<3) array_unshift($tms, '00');

    if ($tms[0]>23) 
    { 
        $d = floor($tms[0] / 24); 
        $tms[0] = $tms[0] % 24; 
    }
    $t = strtotime($tms[0].':'.$tms[1].':'.$tms[2]);

    if ($t>0) {
        $t = getdate($t);
        if (count($tm) > 1) {
            $tm[1] = $tm[1] / pow(10,strlen($tm[1]));
            $t['seconds'] += $tm[1];
        }
    if(!isset($d)) $d = 0;
        return ($n*((($t['seconds'] / 60 + $t['minutes']) / 60) + $t['hours']) / 24) + $d;
    } else
        return $t + $d;
}

function AddtoResults($m_dbh, $eventid, $col, $rs, $num) 
{
    $set   = '';
    $where = '';
    foreach ($num as $v) {
    if ($col[$v] == "time") /* set for range of a few seconds before */
      $set .= ",`$col[$v]`='".addslashes($rs[$v])."'";
    else
      $set .= ",`$col[$v]`='".addslashes($rs[$v])."'";
    $where .= " && `$col[$v]`='".addslashes($rs[$v])."'";
    }

  /* ignore if identical or within a few seconds */
  if (selectone($m_dbh, "rID", "ALResults.rid$eventid", substr($where,4)))
    return 0;

//  echo "INSERT ALResults.rid$eventid SET ".substr($set,1)."<br \>";
    execute($m_dbh, "INSERT ALResults.rid$eventid SET ".substr($set,1));
     $GLOBALS['log']->lwrite( "INSERT ALResults.rid$eventid SET ".substr($set,1). "\n");
    return 1;
}


function selectone($m_dbh, $what, $from, $where = "", $group = "", $order = "") 
{
    $sql = "SELECT $what FROM $from";
    if ($where) $sql .= " WHERE $where";
    if ($group) $sql .= " GROUP BY $group";
    if ($order) $sql .= " ORDER BY $order";
    $sql .= " LIMIT 1";

    if ($m_resultone = mysql_query($sql, $m_dbh)) {
    $rs = mysql_fetch_array($m_resultone, MYSQL_BOTH);
    $m_nf = mysql_num_fields($m_resultone);
    mysql_free_result($m_resultone);
    return $rs;
    } else {
    error_log("Error [ ".mysql_errno()." ] ".mysql_error()."<br /># ".
        strtr($sql,array("<"=>"&lt;",">"=>"&gt;"))."<br />\n");
    }
    return false;
}

function execute($m_dbh, $sql) {
  if (!mysql_query($sql, $m_dbh)) {
    error_log("Error [ ".mysql_errno()." ] ".mysql_error()."<br \>\n");
    return false;
  }
  return true;
}

require "sock/SocketServer.php";

$m_dbname = "ALResults";

if ($argv[1] == "LOC")
{
	$address = "127.0.0.1"; //localhost

	if ($GLOBALS['m_dbh'] = mysql_connect("brandx-test-db.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%")) 
    {
      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
    }
    else 
    {
          $GLOBALS['log']->lwrite( "Error connecting with dev database"); die();
    }
} 
elseif ($argv[1] == "DEV")
{
	$address = "172.31.51.253"; //development server
	

	if ($GLOBALS['m_dbh'] = mysql_connect("brandx-test-db.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%")) 
    {
      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
    }
    else 
    {
          $GLOBALS['log']->lwrite( "Error connecting with dev database"); die();
    }
} elseif ($argv[1] == "PROD") 
{
	$address = "172.31.51.253"; //production server

    if ($GLOBALS['m_dbh'] = mysql_connect("j-chipusa-db-prod.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%")) 
    {
      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
    }
    else 
    {
           $GLOBALS['log']->lwrite( "Error connecting with prod database"); die();
    }
}

$server = new \Sock\SocketServer($port, $address);
$server->init();
$server->setConnectionHandler( 'onConnect' );
$server->listen();
