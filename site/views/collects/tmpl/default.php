<?php

// No direct access
defined('_JEXEC') or die;

jimport('libcs.dates.static');
	
// called by cron to collect data

// https://domain/index.php/?option=com_cs_datavault&view=collects&action=forumssum

$action = JFactory::getApplication()->input->get->get('action', null, 'cmd');
 
switch( $action )	// todo: any new actions need to be added here
{
case "forums_summary":
case "mem_data":
case "web_data":
 	break;
default:
	jexit();	// SILENTLY exit
}

$action($action);

// functions ///////////////////////////////////////////////
 
// old table: joomla.tblparms
// parm_type 	parm_name 			parm_value 	comments
//				postmsgs_last_run 	1577879702 	1203162624
// in j3, cs_report_data is used to store collected data (and information about when it was collected)
// report_type
// mem (membership summary data, collected every 24 hours)
// web (web site usage summary data, collected every 24 hours)
// forums_summary (forums message summary, run every 12 hours) - output is #new messages
// datetimestamp is used to determine whether to collect again (no meta-data needed)
	
// todo: these should be site-specific plugins to allow more collections w/o changing core code
function web_data($action)
{
	echo "collecting $action\n";

	$timestampnow = LibcsDatesStatic::getTimestampNow();	// yyyy-mm-dd hh:ss
	
	$dat = collectWebData($timestampnow);
	putLastRun("webnew",$dat);
	jexit();
}
function collectWebData($timestampnow)
{
	$now = time();		// number of seconds since U*NIX bday

	$db = JFactory::getDBO();
	
	$sql = "SELECT lastVisitDate FROM #__users";
	$db->setQuery($sql);
	$recs = $db->loadAssocList();
	
	$dat = array();
	$dat["users"] = count($recs);

	$sql = "SELECT lastVisitDate FROM #__users WHERE lastVisitDate!='' AND lastVisitDate!='0000-00-00 00:00:00'";
	$db->setQuery($sql);
	$recs = $db->loadAssocList();
	
	$dat["visit_users"] = count($recs);

	$dat["days_since"] = 0;

	foreach ( $recs as $rec )
	{
		$days = abs( LibcsDatesStatic::getDaysApart( LibcsDatesStatic::getUnixTimestampFromMysql(  $rec["lastVisitDate"] ), $now ) ) - 1;
		// todo: bug in getDaysApart - returning 1 when dates are the same day with different hours
		$dat["days_since"] += $days;
		//echo "days=$days for " . $rec["lastVisitDate"] . "<br>";
	}

	$dat["datetimestamp"] = $timestampnow;

	return $dat;
}
function mem_data($action)
{
	echo "collecting $action\n";
	
	$timestampnow = LibcsDatesStatic::getTimestampNow();	// yyyy-mm-dd hh:ss
	
	$dat = collectMembershipData($timestampnow);
	putLastRun("memnew",$dat);
	jexit();
}
function collectMembershipData($timestampnow)
{
	$now = time();		// number of seconds since U*NIX bday
	$today = LibcsDatesStatic::getDateNewYear();	// yyyy-mm-dd
	$dat = array();
	
	$sql = "SELECT id,email,memtype,paidthru FROM #__cs_members WHERE status='Active'";

	$db = JFactory::getDBO();
	$db->setQuery($sql);
	$recs = $db->loadAssocList();

	$dat["active_paid"] = count($recs);
	$memtypes = array();
	$paid_status = array();
	$skip_paid = 0;
	
	foreach ( $recs as $rec )
	{
		$mt = $rec["memtype"];
		$pt = $rec["paidthru"];
		if ( ! empty( $rec["email"] ) )
		{
			if ( ! isset( $dat["email"] ) )
				$dat["email"] = 0;
			$dat["email"]++;
		}
		if ( ! isset( $dat["mtc_".$mt] ) )
			$dat["mtc_".$mt] = 0;
		$dat["mtc_" . $mt]++;	// peg count
		if ( empty( $pt ) || $pt >= $today )
		{
			if ( ! isset( $dat["mtp_" . $mt] ) )
				$dat["mtp_" . $mt] = 0;
			$dat["mtp_" . $mt]++;	// paid up
		}
		if ( empty( $pt ) )
		{
			$skip_paid++;
			continue;
		}

		if ( ! isset( $dat["paid_factor"] ) )
			$dat["paid_factor"] = 0;

		$dat["paid_factor"] += LibcsDatesStatic::getDaysApart( LibcsDatesStatic::getUnixTimestampFromMysql( $pt ), $now );
	}
	$dat["paid_skipped"] = $skip_paid;
	$dat["datetimestamp"] = $timestampnow;

	// todo: other stat - average length of membership

	return $dat;
}
function getLastRun( $action )
{
	$db = JFactory::getDBO();
	$sql = "SELECT id,report_type,datetimestamp FROM `#__cs_report_data` WHERE `report_type` = 'forums_summary' ORDER BY `id` DESC LIMIT 1";
	$db->setQuery($sql);
	$row = $db->loadAssoc();
	if ( ! isset( $row["datetimestamp"] ) )
	{
		putLastRun($action,-1);	// prime table or look at 7 days of messages?
		echo "no $action row in DB";	
		jexit();
	}
	return $row["datetimestamp"];
}
function putLastRun( $action, $data )
{
	$db = JFactory::getDBO();
	$obj = new stdClass();
	$obj->id = 0;
	$obj->datetimestamp = LibcsDatesStatic::getTimeStampNow();
	$obj->report_type = $action;
	if ( is_array($data) )
	{	
		$obj->report_data = serialize($data);
		print_r($data);
	}
	else
	{
		$obj->report_data = $data;
		echo "$action: $data\n";
	}
	
	$result = $db->insertObject( "#__cs_report_data", $obj, 'id' );	// todo: check result
}
function forums_summary($action)
{
	// email summary of forums messages to members

	$out = "";

	$lastrun = getLastRun( $action ); // YY-MM-DD HH:MM:SS
	$cutoff = strtotime($lastrun);	// unix timestamp

	// run no more than every 12 hours (cron initiates) - allow 5 minute slop (300 seconds)
	if ( ( $cutoff + (12 * 60 * 60 ) - 300 ) > time() )
	{
		putLastRun($action."_too_early",-1);
		jexit();
	}	

	// to open forums DB
	jimport('libcs.forums.smf');
	$db = JDatabaseDriver::getInstance( LibcsForumsSmf::getDBConnectionInfo() );

	$messages = smf_get_messages_after( $db, $cutoff );

	$nmsgs = count( $messages );

	if ( $nmsgs > 0 )
	{
		$categories = smf_get_categories($db);
		$boards = smf_get_boards($db);
		$members = smf_get_members($db);

		$out = smf_post_summary( $categories, $boards, $members, $messages  );
		$plural = ( $nmsgs == 1 ) ? "" : "s";
		$email_data = array("subject_data" => "$nmsgs message$plural");
		$email_data["body_data"] = "<p style='font-weight: bold;'>$nmsgs message$plural posted since " . LibcsDatesStatic::getTimeStampNow( $cutoff ) . "</p>$out";

		// import library class for sending email
		
		jimport('libcs.email.sender');
		
		$emailobj = new LibcsEmailSender("forums_summary","com_cs_datavault");

		$emailobj->NoBccSender();
		
		$ret = $emailobj->send( $email_data, true );
		if ( $ret )
		{
			//echo "Emailed to $to!";
		}
		else
			echo " EMAIL to $to FAILED!";
	}

	// save result of summary
	
	putLastRun($action, $nmsgs);
	jexit();
}
function smf_summarize_message( $message, &$categories, &$boards, &$members, $eol = "<br />" )
{
	$body = explode( "<br />", $message['body'] );
	$firstline = "";
	foreach( $body as $line )
	{
		$line = trim( $line );
		if ( ! empty( $line ) )
		{
			$firstline = $eol . substr( $line, 0, 77 ) . "...";
			break;
		}
	}

	// need complete URL to click on in email summary

	// rrtodo: document component dependency com_cs_datavault now dependent on com_cs_payments 
	$org_website = JComponentHelper::getParams("com_cs_payments")->get("org_web_address","");

	return sprintf( "%s, %s, %s$eol%s%s$eol$eol",
			getMemberName( $members, $message['id_member'] ), LibcsDatesStatic::getTimeStampNow( $message['poster_time'] ),
			getCategoryName( $categories, getBoardCategoryID( $boards, $message['id_board'] ) ) . ", " .  getBoardName( $boards, $message['id_board'] ),
			//$row['id_topic'];
			// todo: assumes forums are installed under HTML_ROOT
			sprintf( "<a target='_blank' href='$org_website/forums/index.php?topic=%s.msg%s#msg%s'>" . $message['subject'] . "</a>",
				$message['id_topic'], $message['id_msg'], $message['id_msg'] ), $firstline );
}
function smf_post_summary( &$categories, &$boards, &$members, &$messages  )
{
	$out = "";

	// show oldest to newest in email

	$messages = array_reverse( $messages );

	foreach( $messages as $message )
		$out .= smf_summarize_message( $message, $categories, $boards, $members );

	return $out;
}
function getMemberName( &$members, $id )
{
	return isset( $members[$id]['member_name'] ) ? $members[$id]['member_name'] : "No Member[$id]";
}
function getCategoryName( &$categories, $id )
{
	return isset( $categories[$id]['name'] ) ? $categories[$id]['name'] : "No Category";
}
function getBoardCategoryID( &$boards, $id )
{
	return isset( $boards[$id]['id_cat'] ) ? $boards[$id]['id_cat'] : "";
}
function getBoardName( &$boards, $id )
{
	return isset( $boards[$id]['name'] ) ? $boards[$id]['name'] : "No Board";
}
function smf_get_messages_after( $db, $datetime, $id = 0 )
{
	$sql =  "SELECT * FROM smf_messages WHERE poster_time>=$datetime  ORDER BY id_board";
	$db->setQuery($sql);
	$messages = $db->loadAssocList();
	$n_messages = count( $messages );

//	JFactory::getApplication()->enqueueMessage("$n_messages SMF messages",'success');
//	JFactory::getApplication()->enqueueMessage(str_replace("},","},<br />",json_encode($messages)),'success');

	return $messages;
}
function smf_get_boards( $db, $id = 0 )
{
	$sql =  "SELECT id_board,id_cat,name FROM smf_boards";
	$db->setQuery($sql);
	$boards = $db->loadAssocList();
	$n_boards = count( $boards );

//	JFactory::getApplication()->enqueueMessage("$n_boards SMF boards",'success');
//	JFactory::getApplication()->enqueueMessage(str_replace(",","<br />",json_encode($boards)),'success');

	$ret = array();
	foreach( $boards as $row )
	{
		$ret[$row['id_board']] = array( 'id_cat' => $row['id_cat'], 'name' => $row['name'] );
	}

	return $ret;
}
function smf_get_categories( $db, $id = 0 )
{
	$sql =  "SELECT id_cat,name FROM smf_categories";
	$db->setQuery($sql);
	$cats = $db->loadAssocList();
	$n_cats = count( $cats );

//	JFactory::getApplication()->enqueueMessage("$n_cats SMF categories",'success');
//	JFactory::getApplication()->enqueueMessage(str_replace(",","<br />",json_encode($cats)),'success');

	$ret = array();
	foreach( $cats as $row )
	{
		$ret[$row['id_cat']] = array( 'name' => $row['name'] );
	}

	return $ret;
}
function smf_get_members( $db, $id = 0 )
{
	$sql =  "SELECT id_member,member_name FROM smf_members";
	$db->setQuery($sql);
	$mems = $db->loadAssocList();
	$n_mems = count( $mems );

//	JFactory::getApplication()->enqueueMessage("$n_mems SMF members",'success');
//	JFactory::getApplication()->enqueueMessage(str_replace(",","<br />",json_encode($mems)),'success');

	$ret = array();
	foreach( $mems as $row )
	{
		$ret[$row['id_member']] = array( 'member_name' => $row['member_name'] );
	}

	return $ret;
}
?>