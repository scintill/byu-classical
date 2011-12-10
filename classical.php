<?php
/*
 * http://www.kigkonsult.se/iCalcreator/docs/using.html
 * http://code.google.com/p/calagator/wiki/IcalLocation
 * http://icalvalid.cloudapp.net/
 * http://severinghaus.org/projects/icv/
 * http://arnout.engelen.eu/icalendar-validator/validate/
 * http://www.innerjoin.org/iCalendar/index.html
 */

define('SERVICE_URL', 'https://gamma.byu.edu/ry/ae/prod/registration/cgi/weeklySched.cgi/');
define('SERVICE_KEY', 'WeeklySchedService');
define('COOKIE_NAME', 'BYU-Web-Session');
function getGammaLoginURL($target) { return 'https://gamma.byu.edu/login?target=' . urlencode($target); }

define('TIMEZONE', 'America/Denver');
date_default_timezone_set(TIMEZONE);

define('THIS_URL', 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

// force HTTPS so we can get the secure-only cookie
if (!isset($_SERVER['HTTPS'])) {
    header('Location: '.THIS_URL);
    exit;
}

$calData = getCalendarModel();
if (!$calData) {
        // no cookie -- send them to get us a web-session cookie and come back
        header('Location: ' . getGammaLoginURL(THIS_URL));
        exit;
}

$calendar = createCalendar($calData);
if (isset($_GET['e'])) {
    echo '<pre>';
    echo $calendar->createCalendar();
    echo '</pre>';
} else {
    $calendar->returnCalendar();
}

exit;

function getCalendarModel() {
    $cookie = getCookie();
    if (!$cookie) {
        return null;
    }

    require_once('curl.php');
    $client = new Curl();
    $client->follow_redirects = false;
    $client->headers = array('Accept' => 'application/json', 'Cookie' => COOKIE_NAME.'='.$cookie);
    $response = $client->get(SERVICE_URL);
    if ($response->headers['Status-Code'] != 200) {
        if ($response->headers['Status-Code'] == 302) {
            return null;
        } else {
            throw new RuntimeException('failed getting '.SERVICE_URL.', got '.print_r($response, true));
        }
    }

    $data = json_decode($response->body);

    if (isset($data->{SERVICE_KEY})) {
        $data = $data->{SERVICE_KEY};
    } else {
        $data = null;
    }

    if (!isset($data->request->status) || $data->request->status != 200) {
        throw new RuntimeException('failed getting '.SERVICE_URL.', got '.$response->body);
    }

    $data = $data->response;

    $meetings = array();
    $lastMeeting = null;
    foreach ($data->schedule_table as $schedule_row) {
        if ($schedule_row->sequence == 'child') {
            $meeting = clone $lastMeeting;
        } else {
            $meeting = new stdClass;
        }
        if ($schedule_row->course_title) {
            $meeting->course_title = $schedule_row->course_title;
            $meeting->course = $schedule_row->course;
        }
        $meeting->class_period = $schedule_row->class_period;
        preg_match_all('|([A-Z])([a-z]?)|', $schedule_row->days, $matches);
        $meeting->days = $matches[1];
        $meeting->room = $schedule_row->room;
        $meeting->building = $schedule_row->building;

        $meetings[] = $meeting;
        $lastMeeting = $meeting;
    }

    return (object)array(
        'start' => parseDateToIcal($data->start_year_term),
        'end' => parseDateToIcal($data->end_year_term),
        'meetings' => $meetings,
    );
}

function createCalendar($calData) {
    require_once('iCalCreator.class.php');

    $vcalendar = new vcalendar();
    $vcalendar->setProperty('version', '2.0');
    $vcalendar->setConfig('unique_id', 'classical.byu.edu');
    /* nl = newline, \r\n seems to be the standard for ical. Apparently we need to tell iCalCreator so
     * that it will format its own stuff correctly, but if we put our own linefeeds in, we'll need to follow this.
     */
    $nl = "\r\n";
    $vcalendar->setConfig('nl', $nl);

    $calname = 'Class Schedule';
    $vcalendar->setProperty('X-WR-CALNAME', $calname);

    $vcalendar->setConfig('filename', 'schedule.ics');

    foreach ($calData->meetings as $meeting) {
        $vevent = new vevent();
        $byuDayToICalDay = array('M' => 'MO', 'Tu' => 'TU', 'W' => 'WE', 'Th' => 'TH', 'F' => 'FR', 'S' => 'SA');

        list($classStart, $classEnd) = explode(' - ', $meeting->class_period);
        // put class start and end times on the first day of the semester. recurrence will get it right
        $classStart = getSemesterDate($calData->start, $classStart);
        $classEnd   = getSemesterDate($calData->start, $classEnd);
        $vevent->setProperty('dtstart', $classStart);
        $vevent->setProperty('dtend', $classEnd);

        // stamp the events as being created at the beginning of the semester - don't know if anyone pays attentionto this
        $vevent->setProperty('dtstamp', $calData->start); 

        $days = array();
        foreach ($meeting->days as $byuDay) {
            $days[] = array('DAY' => $byuDayToICalDay[$byuDay]);
        }
        $vevent->setProperty('rrule', array('FREQ' => 'WEEKLY', 'BYDAY' => $days, 'UNTIL' => $calData->end));
        $vevent->setProperty('summary', getSummary($meeting));
        $vevent->setProperty('location', getLocation($meeting));
        
        $vcalendar->addComponent($vevent);
    }

    return $vcalendar;
}

function getSemesterDate($base, $timeStr) {
    $pm = substr($timeStr, -1, 1);
    if ($pm == 'p') {
        $pm = true;
    } else {
        $pm = false;
    }
    list($hour, $minute) = explode(':', $timeStr);
    $hour = (int)$hour;
    $minute = (int)$minute;
    if ($pm) {
        $hour += 12;
    }
    
    $time = $base;
    $time['hour'] = $hour;
    $time['min'] = $minute;
    $time['sec'] = 0;
    $time['tz'] = TIMEZONE;

    return $time;
}

function getSummary($meeting) {
    return $meeting->course_title . ' ('.$meeting->course.')';
}

function getLocation($meeting) {
    return $meeting->room.' '.$meeting->building;
}

function getCookie() {
    $cookie = isset($_COOKIE[COOKIE_NAME]) ? $_COOKIE[COOKIE_NAME] : null;
    return $cookie;
}

function parseDateToIcal($str) {
    $ts = strtotime($str);
    $ical = explode(' ', date('Y n j', $ts));
    return array(
        'year' => (int)$ical[0],
        'month' => (int)$ical[1],
        'day' => (int)$ical[2]
    );
}
