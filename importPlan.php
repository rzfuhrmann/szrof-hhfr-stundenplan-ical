<?php

    include __DIR__ . '/config.php'; 

    $html = file_get_contents(__DIR__ . '/KW33-GS1-HHFR-B.html');

    $lookups = array(); 
    preg_match_all("~\.((x|y|w|h)([a-z0-9]+))\{(left|bottom|width|height):([0-9\.]+)px;\}~i", $html, $matches); 
    foreach ($matches[2] as $m => $namespace){
        if (!isset($lookups[$namespace])) $lookups[$namespace] = array(); 
        if (!isset($lookups[$namespace][$matches[3][$m]])) $lookups[$namespace][(string)$matches[3][$m]] = doubleval($matches[5][$m]); 
    }

    file_put_contents(__DIR__."/lookups.json", json_encode($lookups, JSON_PRETTY_PRINT)); 

    preg_match_all("~.ff([0-9a-z]+)\{([^}]+)\}~i", $html, $matches);
    $ffs = array(); 
    foreach ($matches[1] as $m => $ff){
        if (preg_match("~line-height:([0-9\.]+);~i", $matches[2][$m], $ff_matches)){
            $ffs[$ff] = $ff_matches[1];
        }
    }
    $max_ff = array_keys($ffs, max($ffs));
    $max_ff = "ff".$max_ff[0]; 

    $doc = new DomDocument(); 
    $doc->loadHTML($html); 
    $divs = $doc->getElementsByTagName("div"); 

    function hasClass($elem, $class){
        $classes = classes($elem); 
        if (!$classes) return false; 
        return in_array($class, $classes); 
    }

    function classes($elem){
        $class = $elem->getAttribute("class") ?: ""; 
        $classes = explode(" ", $class); 

        if (!sizeof($classes)) return array(); 
        return $classes; 
    }

    $allcells = array(); 
    foreach ($divs as $div){
        if (hasClass($div, "c")) {
            $this_cell = array(
                "textContent" => trim($div->textContent),
                "lecturename" => false
                //"classes" => classes($div)
            ); 
            foreach ($div->getElementsByTagName("div") as $innerDiv){
                if (hasClass($innerDiv, $max_ff)){
                    $this_cell["lecturename"] = true; 
                }
            }
            foreach (classes($div) as $class){
                if (preg_match("~^(x|y|w|h)([0-9a-z]+)$~i", $class, $matches)){
                    $this_cell[$matches[1]] = $lookups[$matches[1]][$matches[2]];
                }
            }
            $allcells[] = $this_cell; 
        }
    }

    $days = array(); 
    $days_x_min = null; 
    foreach ($allcells as $cell){
        if (preg_match("~(Montag|Dienstag|Mittwoch|Donnerstag|Freitag) ([0-9]{1,2})\.([0-9]{1,2})\.~i", $cell["textContent"], $matches)){
            if ($matches[1] == "Montag"){
                echo "Stunden müssen vor x=".$cell["x"]." liegen\n"; 
                $days_x_min = $cell["x"];
            }
            if ($matches[1] != "Mittwoch"){
//                continue; 
            }
            $this_day = $cell; 
            $this_day["date"] = date("Y-m-d", strtotime("2020-".$matches[3]."-".$matches[2]));
            $this_day["groups"] = array(); 

            // look for groups
            foreach ($allcells as $gcell){
                if (
                    $gcell["x"] > $this_day["x"] - 0.3*$this_day["w"]
                    && $gcell["x"] < $this_day["x"] + $this_day["w"] + 0.3*$this_day["w"]
                    && $gcell["y"] < $this_day["y"] - $this_day["h"]
                    && $gcell["y"] > $this_day["y"] - (3*$this_day["h"])
                    //&& $gcell["y"] < $this_day["y"] + 2*$this_day["h"]
                ){
                    $this_group = $gcell; 
                    $this_group["name"] = $gcell["textContent"]; 
                    $this_group["lectures"] = array(); 
                    //if ($this_group["name"] != "11/12") continue; 
                    

                    foreach ($allcells as $lcell){
                        if (
                            $lcell["x"] > $this_group["x"] - 0.3*$this_group["w"]
                            && $lcell["x"] < $this_group["x"] + $this_group["w"] + 0.3*$this_group["w"]
                            && $lcell["y"] < $this_group["y"]
                            && $lcell["lecturename"] == true
                        ){
                            $lcell["h"] *= 3; 
                            $this_group["lectures"][] = $lcell; 
                        }
                    }

                    $this_day["groups"][$this_group["name"]] = $this_group;
                }
            }


            $days[] = $this_day; 
        }
    }
    //var_dump($days); 


    echo "------------- hours --------------\n"; 
    if ($days_x_min){
        $hours = array(); 
        foreach ($allcells as $cell){
            if (
                $cell["x"] < $days_x_min
                && preg_match("~^([0-9]{1,2}):([0-9]{1,2})$~i", $cell["textContent"], $matches)
            ){
                $ts = (60*60*$matches[1])+(60*$matches[2]); 
                if (sizeof($hours) == 0 || $hours[sizeof($hours)-1]["ts"] != $ts - 60*45){
                    // new
                    $this_hour = $cell; 
                    $this_hour["from"] = $matches[0];
                    $this_hour["ts"] = $ts;
                    $this_hour["h"] *= 3; 
                    $hours[]  = $this_hour;
                } else {
                    $hours[sizeof($hours)-1]["to"] = $matches[0]; 
                }
            }
        }

        function findTime($lecture){
            global $hours; 

            $res = array(
                "start" => null,
                "end" => null
            ); 

            foreach ($hours as $h => $hour){
                if (
                    !$res["start"]
                    && $lecture["y"] <= $hour["y"] + 0.3*$hour["h"]
                    && $lecture["y"] >= $hour["y"] - $hour["h"]
                ){
                    $res["start"] = $hour["from"]; 
                }

                if (
                    !$res["end"]
                    && $lecture["y"] - $lecture["h"] <= $hour["y"] + 0.3*$hour["h"]
                    && $lecture["y"] - $lecture["h"] >= $hour["y"] - $hour["h"]
                ){
                    $res["end"] = $hour["to"]; 
                }
            }
            return $res; 
        }

        foreach ($days as $d => $day){
//            if ($day["date"] != "2020-08-12") continue; 

            foreach ($day["groups"] as $g => $group){
//                if ($g != "11/12") continue; 

                foreach ($group["lectures"] as $l => $lecture){
                    $times = findTime($lecture); 
                    $days[$d]["groups"][$g]["lectures"][$l]["from"] = $times["start"];
                    $days[$d]["groups"][$g]["lectures"][$l]["to"] = $times["end"];

                    foreach ($allcells as $cell){
                        if (
                            !$cell["lecturename"]
                            && $cell["x"] > $lecture["x"] - $lecture["w"]
                            && $cell["x"] < $lecture["x"] + 2*$lecture["w"]
                            && $cell["y"] < $lecture["y"]
                            && $cell["y"] > $lecture["y"] - 2*$lecture["h"]
                        ){
                            $days[$d]["groups"][$g]["lectures"][$l]["lecturer"] = $cell["textContent"];
                            break; 
                        }
                    }
                }

                $groupnames = explode("/", $g); 
                if (sizeof($groupnames) > 1){
                    foreach ($groupnames as $groupname){
                        $groupname = "1.".$groupname;
                        $days[$d]["groups"][$groupname] = $days[$d]["groups"][$g];
                        $days[$d]["groups"][$groupname]["name"] = $groupname; 
                    }

                    unset($days[$d]["groups"][$g]);
                }
            }
        }

        // DB IMPORT
        foreach ($days as $d => $day){
            foreach ($day["groups"] as $g => $group){
                foreach ($group["lectures"] as $l => $lecture){
                    $this_lecture = array(
                        "name" => $lecture["textContent"],
                        "date" => $day["date"],
                        "lecturer" => $lecture["lecturer"],
                        "tfrom" => $lecture["from"],
                        "tto" => $lecture["to"],
                        "group" => $g,
                        "lastupdate" => time(),
                        "created" => time()
                    );

                    $DB->insert()->into("lectures")->values($this_lecture)->on_duplicate_key_update_with_value(array("name","date","lecturer","tfrom","tto","lastupdate"))->exec(); 
                }
            }
        }
    } else {
        echo "Sorry, we can't find the hour beginnings... :("; 
    }


    file_put_contents(__DIR__ . '/days.json', json_encode($days, JSON_PRETTY_PRINT)); 
?>