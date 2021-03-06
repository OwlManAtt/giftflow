<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
*	The Util library handles various utility functions.
*	
*	@author Brandon Jackson
*	@package Libraries
*/


class Util
{

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		
	}
	/**
	*	Performs tasks at the beginning of each controller
	*	@return void
	*/
	public function config()
	{
		if( !empty($this->CI->session->userdata['role']) && $this->CI->session->userdata['role']=="admin" )
		{
			if( !empty($this->CI->session->userdata['profiler']) && $this->CI->session->userdata['profiler'] == TRUE && !$this->CI->input->is_ajax_request())
			{
				$this->CI->output->enable_profiler('true');
			}
		}
	}
	
	/**
	*	Parses global variables which are returned in an array and used
	*	to define the $this->data variable in all controllers
	*
	*	@param array $options
	*	@return array
	*/
	public function parse_globals( $options = array() )
	{
	
		Console::logSpeed('start Util::parse_globals()');

		$globals = array();
		
		// Determine if we are on a local host or on the live site
		$host = explode(".", $_SERVER['HTTP_HOST']);
		$localhost = FALSE;
		
		//assume ip address or localhost
		if (sizeof($host) > 3 || sizeof($host) == 1) 
		{ 
			$domain = $_SERVER['HTTP_HOST'];
			if (sizeof($host) == 1)
			{
				$localhost = TRUE;
			}
		} 
		else
		{
			$domain = $host[sizeof($host)-2] . "." . $host[sizeof($host) - 1];
		}
		$globals['localhost'] = $localhost;

		// Set userdata
		if($this->CI->session->userdata('user_id'))
		{
			$globals['logged_in'] = TRUE;
			$globals['logged_in_user_id'] = $this->CI->session->userdata('user_id');
			$globals['logged_in_screen_name'] = $this->CI->session->userdata('screen_name');
			$globals['logged_in_email'] = $this->CI->session->userdata('email');
			$globals['userdata'] = array(
				'logged_in' => TRUE,
				'role'=>$this->CI->session->userdata('role'),
				'level'=>$this->CI->session->userdata('level'),
				'user_id'=>$this->CI->session->userdata('user_id'),
				'email'=>$this->CI->session->userdata('email'),
				'username'=>$this->CI->session->userdata('username'),
				'screen_name'=>$this->CI->session->userdata('screen_name'),
				'first_name'=>$this->CI->session->userdata('first_name'),
				'last_name'=>$this->CI->session->userdata('last_name'),
				'default_photo_thumb_url'=>$this->CI->session->userdata('photo_thumb_url'),
				'default_photo_url'=>$this->CI->session->userdata('photo_url'),
				'language'=>$this->CI->session->userdata('language'),
				'timezone'=>$this->CI->session->userdata('timezone')
			);
			
			// Set first name to screen_name if needed
			if(empty( $globals['userdata']['first_name'] ))
			{
				$globals['userdata']['first_name'] = $globals['userdata']['screen_name'];
			}
			

			//Set display name
			
			$display_name = '';
			if(strlen($globals['userdata']['screen_name']) > 12) {
				$names_array = mb_split(" ",$globals['userdata']['screen_name']);
				if(strlen($names_array[0]) < 12) {
					$display_name = $names_array[0];
				} else {
					$display_name = 'You';
				}
			} else {
				$display_name = $globals['userdata']['screen_name'];
			}
			$globals['userdata']['display_name'] = $display_name;

			
			// Set Location Data
			
			// Iterate over list of location fields, setting Location field if data
			$globals['userdata']['location'] = new stdClass(); //(object) array();

			$location_fields = array("longitude","latitude","address","city","state");

			foreach($location_fields as $field)
			{
				if(!empty($this->CI->session->userdata['location_'.$field]))
				{
					$globals['userdata']['location']->$field = $this->CI->session->userdata('location_'.$field);
				}
			}

			//check for outstanding notifications
			$globals['inboxCount'] = $this->activeInbox($this->CI->session->userdata('user_id'));
			$globals['activeInbox'] = ($globals['inboxCount'] > 0)? TRUE : FALSE;

		}
		else
		{
			$this->CI->config->load('account',TRUE);
			$fbook = $this->CI->config->config['account'];

			//load the facebook sdk
			if(defined('FBOOK_APP_ID') && defined('FBOOK_SECRET'))
			{
				require_once('assets/facebook-php-sdk/src/facebook.php');
				$config = array (	
					"appId"=> FBOOK_APP_ID,
					"secret"=> FBOOK_SECRET,
					"fileUpload"=>true
				);
	
				$this->facebook = new Facebook($config);
				$params = array(
					'scope' => 'email, user_photos, publish_stream',
					'redirect_uri' => site_url('member/login/?redirect=').$this->CI->uri->uri_string()
				);
	
				$globals['fbookUrl'] = $this->facebook->getLoginUrl($params);
			}
			
			//redirect URL for logins
			//This is overriden by action use cases, like where a user tries to request a good

			$globals['dropdown_login_redirect'] = current_url();

			$globals['logged_in'] = FALSE;
			$globals['userdata'] = array();
		}

		//Important note about locations. Locations are almost always stored as objects...
		//EXCEPT for the session->userdata which is an flat array with each value as 'location_whatever'
		//Geocode via IP address if session has no location
		$sess_locate = $this->CI->session->userdata('location_longitude');
		if(empty($sess_locate))
		{
			$this->CI->load->library('geo');
			$this->CI->load->library('auth');
			//die("processing location");
			$globals['userdata']['location'] = $this->CI->geo->geocode_ip();
			$this->CI->auth->update_session_location($globals['userdata']['location']);

		}
		//session location is set, but globals is not
		else if(empty($globals['userdata']['location']))
		{
			$globals['userdata']['location'] = new stdClass();
			
			$properties = array(
				"latitude","longitude","address","city","state","country"
			);
			//translate flat session array into globals location object
			foreach($properties as $property)
			{
				$globals['userdata']['location']->$property = $this->CI->session->userdata("location_".$property);
			}
		}

		/* Get and trim location for header */
		$header_location = (isset($globals['userdata']['location']->city)) ? $globals['userdata']['location']->address: 'Add your location';
		$locate_array = explode(" ",$header_location);

		$i=0;
		$rebuild = '';
		while($i < count($locate_array)) {
			if(strlen($rebuild.$locate_array[$i]) < 14) {
				$rebuild .= " ".$locate_array[$i];
				$i++;
			} else {
				$i = 100;
			}
		}
		$globals['header_location'] = rtrim($rebuild, ",");

		$globals['alert_success'] = "";
		$globals['alert_error'] = "";
		
		// Is this an AJAX request?
		$globals['is_ajax'] = $this->CI->input->is_ajax_request();


		
		// Load URI segments as array so they can be used in conditionals
		// ( loading their values from the URI library often throws an error
		// if you try to do this)
		$globals['segment'][1] = $this->CI->uri->segment(1);
		$globals['segment'][2] = $this->CI->uri->segment(2);
		$globals['segment'][3] = $this->CI->uri->segment(3);
		$globals['segment'][4] = $this->CI->uri->segment(4);
		
		// Set Default Facebook Open Graph Tags
		$globals['open_graph_tags'] = array(
			'og:url' => current_url(),
			'og:site_name' => 'GiftFlow'
		);
		
		if(defined('FBOOK_APP_ID'))
		{
			$globals['open_graph_tags']['fb:app_id']= FBOOK_APP_ID;
		}
		
		Console::logSpeed('end Util::parse_globals()');
		
		return $globals;
	}


	/**
	 * Checks if user has outstanding notifications
	 *
	 * @param user_id
	 * returns int 
	 * @author Hans Schoenburg
	 */

	public function activeInbox($user_id)
	{

		$results = $this->CI->db->select('N.event_id, N.user_id, N.enabled')
					->from('notifications AS N')
					->where('N.user_id',$this->CI->session->userdata('user_id'))
					->where('N.enabled', 1)
					->get()
					->result();


		return count($results);
	}

	public function clearActiveInbox($user_id)
	{
		$this->CI->db->where('user_id', $user_id)
			->update('notifications', array('enabled' => 0));
	}



	/**
	*	Calculates time ago string
	*	For example, "This post was created 4 minutes ago" instead of displaying
	*	a date
	*
	*	@param timestamp $datefrom
	*	@param timestamp $dateto
	*/
	public function time_ago( $datefrom, $dateto=-1 )
	{
		// Defaults and assume if 0 is passed in that
		// its an error rather than the epoch
		
		if($datefrom<=0)
		{ 
			return "A long time ago"; 
		}
		if($dateto==-1) 
		{
			$dateto = time(); 
		}
		
		// Calculate the difference in seconds betweeen
		// the two timestamps
		
		$difference = $dateto - $datefrom;
		
		// If difference is less than 60 seconds,
		// seconds is a good interval of choice
		
		if($difference < 60)
		{
			$interval = "s";
		}
		
		// If difference is between 60 seconds and
		// 60 minutes, minutes is a good interval
		elseif($difference >= 60 && $difference<60*60)
		{
			$interval = "n";
		}
		
		// If difference is between 1 hour and 24 hours
		// hours is a good interval
		elseif($difference >= 60*60 && $difference<60*60*24)
		{
			$interval = "h";
		}
		
		// If difference is between 1 day and 7 days
		// days is a good interval
		elseif($difference >= 60*60*24 && $difference<60*60*24*7)
		{
			$interval = "d";
		}
		
		// If difference is between 1 week and 30 days
		// weeks is a good interval
		elseif($difference >= 60*60*24*7 && $difference <
		60*60*24*30)
		{
			$interval = "ww";
		}
		
		// If difference is between 30 days and 365 days
		// months is a good interval, again, the same thing
		// applies, if the 29th February happens to exist
		// between your 2 dates, the function will return
		// the 'incorrect' value for a day
		elseif($difference >= 60*60*24*30 && $difference <
		60*60*24*365)
		{
			$interval = "m";
		}
		
		// If difference is greater than or equal to 365
		// days, return year. This will be incorrect if
		// for example, you call the function on the 28th April
		// 2008 passing in 29th April 2007. It will return
		// 1 year ago when in actual fact (yawn!) not quite
		// a year has gone by
		elseif($difference >= 60*60*24*365)
		{
			$interval = "y";
		}
		
		// Based on the interval, determine the
		// number of units between the two dates
		// From this point on, you would be hard
		// pushed telling the difference between
		// this function and DateDiff. If the $datediff
		// returned is 1, be sure to return the singular
		// of the unit, e.g. 'day' rather 'days'
		
		switch($interval)
		{
		case "m":
		$months_difference = floor($difference / 60 / 60 / 24 /
		29);
		while (mktime(date("H", $datefrom), date("i", $datefrom),
		date("s", $datefrom), date("n", $datefrom)+($months_difference),
		date("j", $dateto), date("Y", $datefrom)) < $dateto)
		{
			$months_difference++;
		}
		$datediff = $months_difference;
		
		// We need this in here because it is possible
		// to have an 'm' interval and a months
		// difference of 12 because we are using 29 days
		// in a month
		
		if($datediff==12)
		{
			$datediff--;
		}
		
		$res = ($datediff==1) ? "$datediff month ago" : "$datediff
		months ago";
		break;
		
		case "y":
		$datediff = floor($difference / 60 / 60 / 24 / 365);
		$res = ($datediff==1) ? "$datediff year ago" : "$datediff
		years ago";
		break;
		
		case "d":
		$datediff = floor($difference / 60 / 60 / 24);
		$res = ($datediff==1) ? "$datediff day ago" : "$datediff
		days ago";
		break;
		
		case "ww":
		$datediff = floor($difference / 60 / 60 / 24 / 7);
		$res = ($datediff==1) ? "$datediff week ago" : "$datediff
		weeks ago";
		break;
		
		case "h":
		$datediff = floor($difference / 60 / 60);
		$res = ($datediff==1) ? "$datediff hour ago" : "$datediff
		hours ago";
		break;
		
		case "n":
		$datediff = floor($difference / 60);
		$res = ($datediff==1) ? "$datediff minute ago" :
		"$datediff minutes ago";
		break;
		
		case "s":
		$datediff = $difference;
		$res = ($datediff==1) ? "$datediff second ago" :
		"$datediff seconds ago";
		break;
		}
		return $res;
	}
	
	/**
	*	Converts UTC timestamp into user's local time
	*	Gets timezone from session data if available
	*	@param str $timestamp		Can be either integers or string
	*	@param str $format
	*	@return str
	*/
	function user_date( $timestamp, $format = "F jS Y g:ia" )
	{                
		if(is_string($timestamp))
		{
			$timestamp = strtotime($timestamp);
		}
		$timezone = $this->CI->session->userdata('timezone') ? $this->CI->session->userdata('timezone') : "America/New_York";

		$date = new DateTime();
		$date->setTimestamp($timestamp);
		$date->setTimezone(new DateTimeZone($timezone));
		return $date->format($format);
	}
		
	function json($json)
	{
		$this->CI->output->set_header("Content-Type:application/json");
		$this->CI->output->set_output($json);
	}

}
