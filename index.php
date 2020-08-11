<?php
    /**
     * outputs an iCal file to be subscribed to by Apple Calender oder Microsoft Outlook
     * Parameters:
     * - gruppe
     *   if "gruppe" is set to a specific group identifier (e.g. 1.12) the specific lectures are returned. 
     *   if "gruppe" is set to "all" all lectures from all groups are returned
     *   default is "1.11"
     * 
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * @copyright   (C) 2020 Sebastian Fuhrmann
     * @version     2020-08-11
     * 
     */

    include __DIR__ . '/config.php'; 

    header('Content-type: text/calendar; charset=utf-8');
	header('Content-Disposition: inline; filename=calendar.ics');

	$ical = ""; 
	$ical .= "BEGIN:VCALENDAR\n";
	$ical .= "PRODID:RZ Fuhrmann for HHFR\n";
	$ical .= "VERSION:2.0\n";
	$ical .= "REFRESH-INTERVAL;VALUE=DURATION:PT1M\n";
	$ical .= "X-PUBLISHED-TTL:PT1M\n";
	$ical .= "BEGIN:VTIMEZONE\n";
	$ical .= "TZID:Europe/Berlin\n";
	$ical .= "X-LIC-LOCATION:Europe/Berlin\n";
	$ical .= "BEGIN:DAYLIGHT\n";
	$ical .= "TZOFFSETFROM:+0100\n";
	$ical .= "TZOFFSETTO:+0200\n";
	$ical .= "TZNAME:CEST\n";
	$ical .= "DTSTART:19700329T020000\n";
	$ical .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\n";
	$ical .= "END:DAYLIGHT\n";
	$ical .= "BEGIN:STANDARD\n";
	$ical .= "TZOFFSETFROM:+0200\n";
	$ical .= "TZOFFSETTO:+0100\n";
	$ical .= "TZNAME:CET\n";
	$ical .= "DTSTART:19701025T030000\n";
	$ical .= "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\n";
	$ical .= "END:STANDARD\n";
	$ical .= "END:VTIMEZONE\n";
	$ical .= "METHOD:PUBLISH\n";
	$name_lookup = array(
		"Meth" => "Methodenlehre",
		"ESt" => "Einkommenssteuer",
		"AO" => "Abgabenordnung?",
		"ÖR" => "Öffentliches Recht?",
		"Bil" => "Bilanzierung",
		"USt" => "Umsatzsteuer",
		"PR" => "Privates Recht",
	);
    $groups = array(); 
    
    $dbres = $DB->query("SELECT * FROM lectures;")->fetchAll();
    foreach ($dbres as $row){
        if (!isset($groups[$row["group"]])) $groups[$row["group"]] = array("events" => array());

		$row["categories"] = array("Lecture", "Vorlesung", "Unterricht");
        $groups[$row["group"]]["events"][] = $row;

	}
	
	// meals
	$dbmeals = $DB->query("SELECT * FROM meals m JOIN (SELECT DISTINCT l.group, l.date FROM lectures l) l1 ON l1.`group` = m.`group` AND l1.date >= m.`datefrom` AND l1.date <= m.`dateto`;")->fetchAll(); 
	foreach ($dbmeals as $meal){
		$this_event = array(
			"name" => $meal["name"],
			"date" => $meal["date"],
			"tfrom" => $meal["tfrom"],
			"tto" => $meal["tto"],
			"categories" => array("Essen", "Meal", "Freizeit"),
		); 
		if ($meal["type"] == "meal"){
			$this_event["location"] = "Mensa"; 
		}

		$groups[$meal["group"]]["events"][] = $this_event; 
	}

	// defaults
	$dbgroups = $DB->query("SELECT * FROM `groups` g;")->fetchAll(); 
	foreach ($dbgroups as $group){
		if (isset($groups[$group["group"]])){
			if (!isset($groups[$group["group"]]["defaults"])) $groups[$group["group"]]["defaults"] = array(); 
			$groups[$group["group"]]["defaults"]["location"] = $group["default_location"];
		}
	}

    $group = "1.11";
	$allgroups = array($group => $groups[$group]); 
	if (isset($_REQUEST["gruppe"]) && $_REQUEST["gruppe"] == "all"){
		$allgroups = $groups;
	} else {
		$allgroups = array($group => $groups[$group]); 
	}
    $events = array();

	foreach ($allgroups as $group => $groupdata){
		foreach ($groupdata["events"] as $event){
			$this_ical = "";
			$this_ical .= "BEGIN:VEVENT\n";
			$time = strtotime($event["date"]." ".$event["tfrom"]);
			$this_ical .= "DTSTART;TZID=Europe/Berlin:".date("Ymd", $time)."T".date("His", $time)."\n";
			$time = strtotime($event["date"]." ".$event["tto"]);
			$this_ical .= "DTEND;TZID=Europe/Berlin:".date("Ymd", $time)."T".date("His", $time)."\n";
			//$ical .= "EXDATE;TZID=Europe/Berlin:20200805T190000\n";
			if (isset($event["location"])){
				$this_ical .= "LOCATION:".$event["location"]."\n";
			} elseif (isset($groupdata["defaults"]["location"])) {
				$this_ical .= "LOCATION:".$groupdata["defaults"]["location"]."\n";
			} else {
				//$ical .= "LOCATION:".$event["location"]."\n";
			}
			
			$this_ical .= "DTSTAMP:".date("Ymd")."T".date("His")."Z\n";
			$this_ical .= "UID:".md5($group.$event["date"].$event["tfrom"].[$event["name"]])."\n";
//			$this_ical .= "STATUS:CONFIRMED\n";
			$debuginfos = array(
				"last_download" => date("Y-m-d H:i:s"),
				"categories" => isset($event["categories"])?implode(",", $event["categories"]):"",
				"pdf" => isset($event["file"])?$event["file"]:"unknown",
				"pdfconverted" => isset($event["lastupdate"])?date("Y-m-d H:i:s", $event["lastupdate"]):"",
			); 
			$debug_text = array();
			foreach ($debuginfos as $info => $value){
				$debug_text[] = $info . ": ". $value; 
			}
			$description = array(
				"Angaben ohne Gewähr.",
				"Probleme u. Fragen gern an ical-hhfr@rz-fuhrmann.de",
				"Telegram: https://t.me/studienzentrum",
				"",
				//"------- DEBUG -------",
				implode("\\n", $debug_text)
			);
			$this_ical .= "DESCRIPTION:".implode("\\n", $description)."\n";
			if (isset($name_lookup[$event["name"]])) $event["name"] = $name_lookup[$event["name"]];
			if (isset($event["categories"])){
				if (in_array("Meal", $event["categories"])){
					$event["name"] = "🍴 ".$event["name"]; 
				} else if (in_array("Lecture", $event["categories"])){
					$event["name"] = "📖 ".$event["name"]; 
				}

			}
			if (isset($event["lecturer"])){
				$this_ical .= "SUMMARY:".$event["name"]." (".$event["lecturer"].")\n";
				$this_ical .= "RESOURCES:".$event["lecturer"]."\n";
			} else {
				$this_ical .= "SUMMARY:".$event["name"]."\n";	
			}
			if (isset($event["categories"])){
				$this_ical .= "CATEGORIES:".implode(",", $event["categories"])."\n";
			} else {
				$this_ical .= "CATEGORIES:Termin\n";
			}
			
			$this_ical .= 'CONTACT:Sebastian Fuhrmann\, +49 151 41456541\,ical-hhfr@rz-fuhrmann.de'."\n";
			$this_ical .= "BEGIN:VALARM\nTRIGGER:-PT15M\nACTION:DISPLAY\nEND:VALARM\n";
			$this_ical .= "END:VEVENT\n";
			$events[strtotime($event["date"]." ".$event["tfrom"]) . "_" . md5($group.$event["date"].$event["tfrom"].[$event["name"]])] = $this_ical;
		}
	}

	ksort($events);
	foreach ($events as $event){
		$ical .= $event; 
	}
	
	$ical .= "END:VCALENDAR\n";
	$ical .= "\n";
	echo $ical; 
	exit; 
?>