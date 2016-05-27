<?php

require "Logging.php";

error_reporting(E_ALL);
set_time_limit(0);

// Logging class initialization update 2.0
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
$arg_type = "LOC"; //default


/**
 * Connection handler
 */
function onConnect( $client ) {

	$m_dbname = "ALResults";
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

     //define database to use
	if ($GLOBALS['arg_type'] == "LOC")
	{
		if ($GLOBALS['m_dbh'] = mysql_connect("brandx-test-db.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%", true)) 
	    {
	      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
	      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
	    }
	    else 
	    {
	    	error_log("Error connecting with dev database");
	        $GLOBALS['log']->lwrite("Error connecting with dev database"); die();
	    }
	} 
	elseif ($GLOBALS['arg_type'] == "DEV")
	{
		if ($GLOBALS['m_dbh'] = mysql_connect("brandx-test-db.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%", true)) 
	    {
	      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
	      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
	    }
	    else 
	    {
	    	error_log("Error connecting with dev database");
	        $GLOBALS['log']->lwrite( "Error connecting with dev database"); die();
	    }
	} elseif ($GLOBALS['arg_type'] == "PROD") 
	{
	    if ($GLOBALS['m_dbh'] = mysql_connect("j-chipusa-db-prod.cr6c86g1nups.us-east-1.rds.amazonaws.com","athlete2","runner2%", true)) 
	    {
	      mysql_select_db($m_dbname, $GLOBALS['m_dbh']);
	      mysql_set_charset('utf8', $GLOBALS['m_dbh']);
	    }
	    else 
	    {
	    	error_log("Error connecting with dev database");
	        $GLOBALS['log']->lwrite( "Error connecting with prod database"); die();
	    }
	}
	
	$read = '';
	printf( "[%s] Connected at port %d\n", $client->getAddress(), $client->getPort() );
    $GLOBALS['log']->lwrite("Connected at: ". $client->getAddress() ." port: ". $client->getPort() );

	$data = '';

	while( true ) 
	{
		$read = $client->read();

		if( $read != '' ) 
		{
            $GLOBALS['log']->lwrite( "first read: " . $read);
            echo "first read: " . $read;

			//establish connection with receiver
	        if (substr($read, 0, 1) == 'N') 
	        { 
	            $GLOBALS['log']->lwrite( "event: " . $read . "\n");

	            echo "event: " . $read . "\n";

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
	        if (substr($read, 0, 1) == 'D' OR substr($read, 0, 1) != 'N') 
	        {
	            $GLOBALS['log']->lwrite( "inside D data: $read \n");

	            echo "inside D data: $read \n";

	            //online data
	            if(strlen($read) <= 40)
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

		                $return_value = AddtoResults($event_id, $col, $val, $num);

		                if ($return_value == 1) 
		                {
		                    $time = time();  
		                       $GLOBALS['log']->lwrite( 'inserted row with transid = ' . $val[1] . "\n");
		                       echo 'inserted row with transid = ' . $val[1] . "\n";
		                }else
		                {
		                	$GLOBALS['log']->lwrite( 'row NO inserted transid = ' . $val[1] . "\n");
		                    echo 'row NO inserted transid = ' . $val[1] . "\n";
		                }
		            }

		         // offline data sent by receiver
		        }else
		        {
		        	$GLOBALS['log']->lwrite('Disconnected data feature'. "\n");
		        	echo 'Disconnected data feature'. "\n";

		        	//convert packet to hex
		        	$hex = bin2hex($read);
                	//$GLOBALS['log']->lwrite( "event data from event ID: " . $event_id . " hex: $hex \n");
          
          			//replace _end_of_line to '-'
         	     	$hex_replaced = str_replace("0d", "2d", $hex);
            	   // $GLOBALS['log']->lwrite( "hex replaced: $hex_replaced \n");

         	     	//convert hex replaced in ascii again, with new _end_of_line
         	     	$read = pack('H*', $hex_replaced);

         	     	$GLOBALS['log']->lwrite( "final read: $read \n");

            	    //string buffer
            	    $GLOBALS['log']->lwrite("data: ".$data."\n");


		        	$str = $read;
					$strlen = strlen( $str );
					for( $i = 0; $i <= $strlen; $i++ ) 
					{
					    $char = substr( $str, $i, 1 );
					    if ($char == "-")
					    {
					    	$GLOBALS['log']->lwrite( "row: ". $data ."\n");
					    	echo "row: " . $data . "\n";

					    	//regular expresion used to parse data
				            $pattern = "/^(.)(.{6}) (.{8})\.(.{2})(.{2}) 1(.)(.{3})(.{4})/s";

				            if (preg_match($pattern, substr($data, 1, strlen($data)), $pm)) 
				            {
				                $val[0] = $pm[1];                       /* box */
				                $val[1] = $pm[2];                       /* transid */
				                $val[2] = cvTimeDbl($pm[3].".".$pm[4]); /* time.msecs */
				                $val[3] = $pm[5];                       /* pc_id (antenna) */
				                $val[4] = ltrim($pm[7]);                /* lap (hits) */

				                $GLOBALS['log']->lwrite("formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n");

				                echo "formatted-> box: " . $val[0] . " transid: " . $val[1] . " time.msecs: " . $val[2] . " antenna: " . $val[3] . " hits: " . $val[4] ."\n";

				                $return_value = AddtoResults($event_id, $col, $val, $num);

				                if ($return_value == 1) 
				                {
				                    $time = time();  
				                       $GLOBALS['log']->lwrite( 'inserted row with transid = ' . $val[1] . "\n");
				                       echo 'inserted row with transid = ' . $val[1] . "\n";
				                }else
				                {
		                			$GLOBALS['log']->lwrite( 'row NO inserted transid = ' . $val[1] . "\n");
		                    		echo 'row NO inserted transid = ' . $val[1] . "\n";
		                		}
				            }

					    	$data = '';
					    }else
					        $data .= $char;

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
			printf( "[%s] Disconnected in while \n", $client->getAddress() );
			return false;
		}
		

	}
	$client->close();
	mysql_close();
	printf( "[%s] Disconnected in onConnect \n", $client->getAddress() );
	
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

function AddtoResults($eventid, $col, $rs, $num) 
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
    if (selectone($GLOBALS['m_dbh'], "rID", "ALResults.rid$eventid", substr($where,4)))
    {
    	//mysql_close();
    	return 0;
    }
    

//  echo "INSERT ALResults.rid$eventid SET ".substr($set,1)."<br \>";
    $GLOBALS['log']->lwrite( "execute INSERT ALResults.rid$eventid SET ".substr($set,1). "\n");
    execute($GLOBALS['m_dbh'], "INSERT ALResults.rid$eventid SET ".substr($set,1));
   // mysql_close();
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
    	$GLOBALS['log']->lwrite("Error in select method [ ".mysql_errno()." ] ".mysql_error()."\n");
    	error_log("Error in select method [ ".mysql_errno()." ] ".mysql_error()."\n");
    	$GLOBALS['log']->lwrite(strtr($sql, array("<"=>"&lt;",">"=>"&gt;"))."\n");
    	echo strtr($sql, array("<"=>"&lt;",">"=>"&gt;"))."\n";
       
    }
    return false;
}

function execute($m_dbh, $sql) {
  if (!mysql_query($sql, $m_dbh)) {
    error_log("Error [ ".mysql_errno()." ] ".mysql_error()."<br \>\n");
    echo "Error [ ".mysql_errno()." ] ".mysql_error()."<br \>\n";
    $GLOBALS['log']->lwrite("Error in execute method [ ".mysql_errno()." ] ".mysql_error()."\n");
    return false;
  }
  return true;
}

require "sock/SocketServer.php";

if ($argv[1] == "LOC")
{
	$address = "127.0.0.1"; //localhost

	$GLOBALS['arg_type'] = $argv[1];

} 
elseif ($argv[1] == "DEV")
{
	$address = "172.31.51.253"; //development server
	
	$GLOBALS['arg_type'] = $argv[1];

} elseif ($argv[1] == "PROD") 
{
	$address = "172.31.51.253"; //production server

	$GLOBALS['arg_type'] = $argv[1];

}

$server = new \Sock\SocketServer($port, $address);
$server->init();
$server->setConnectionHandler( 'onConnect' );
$server->listen();
