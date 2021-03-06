<?php

//  BRIGHTALLY CUSTOM CODE
//  Coder: Ted vd Brink
//  Contact: ted.vandenbrink@brightalley.nl
//  Date: 6 juni 2012
//
//  Description: Enrols users into a course by allowing a user to upload an csv file with only email adresses
//  Using this block allows you to use CSV files with only emailaddress
//  After running the upload you can download a txt file that contains a log of the enrolled and failed users.

//  License: GNU General Public License http://www.gnu.org/copyleft/gpl.html

function block_csv_enrol_enrol_users($courseid,$csvcontent)
{
	global $DB, $CFG;
	require_once($CFG->libdir.'/enrollib.php');
	$fieldname = block_csv_enrol_get_field();
	
	//get enrolment instance (manual and student)
	$instances = enrol_get_instances($courseid, false);
	$enrolment = "";
	foreach ($instances as $instance) {
		if ($instance->enrol === 'manual') {
		$enrolment = $instance;
					break;
		}
	}

	//get enrolment plugin
	$manual = enrol_get_plugin('manual');
	$context = context_course::instance($courseid);

	$stats = new StdClass();
	$stats->success = $stats->failed = 0; //init counters
	$log = get_string('enrolling','block_csv_enrol')."\r\n";

	$lines = explode("\n",$csvcontent);
	foreach ($lines as $line) {
		if($line=="") continue;
		$user = $DB->get_record('user', array($fieldname => trim($line)));
		if($user && !$user->deleted) {
			if(is_enrolled($context,$user)) {
				$log .= get_string('enrollinguser','block_csv_enrol',fullname($user).' ('.$user->username.')')."\r\n";
			} else {
				$log .= get_string('alreadyenrolled','block_csv_enrol',fullname($user).' ('.$user->username.')')."\r\n";
				$manual->enrol_user($enrolment,$user->id,$enrolment->roleid,time());
			}
			$stats->success++;
		} else {			
			$a = new stdClass;
			$a->field = $fieldname;
			$a->val = trim($line);
			
			$log .= get_string('fieldnotfound','block_csv_enrol', $a)."\r\n";
			$stats->failed++;
		}
	}	
	$log .= get_string('done','block_csv_enrol')."\r\n";
	$log = get_string('status','block_csv_enrol',$stats).' '.get_string('enrolmentlog','block_csv_enrol')."\r\n\r\n".$log;
	return $log;
}

function block_csv_enrol_get_field() {

	$fieldid = (int)get_config('csv_enrol', 'field');
	
	switch($fieldid) {
	case 0:
	default:
		return 'username';
	case 1:
		return 'email';
	case 2:
		return 'idnumber';
	}
}
