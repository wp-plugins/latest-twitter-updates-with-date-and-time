<?php
/*
Plugin Name: Latest twitter updates with date and time
Plugin URI: http://www.opensourcetechnologies.com/
Description: Creates a sidebar widget that displays the latest twitter updates for any user with date and time of tweet created.
Author: opensourcetech
Version: 1.0
Author URI: http://www.opensourcetechnologies.com/
*/

class latest_twitter_widget extends WP_Widget {

	function latest_twitter_widget() {
		// widget actual processes
		parent::WP_Widget( /* Base ID */'latest_twitter_widget', /* Name */'Latest twitter updates', array( 'description' => 'Displays your latest twitter.com updates' ) );
	}

	function form($instance) {
		// outputs the options form on admin
		if ( !function_exists('quot') ){
			function quot($txt){
				return str_replace( "\"", "&quot;", $txt );
			}
		}

		// format some of the options as valid html
		$username = htmlspecialchars($instance['user'], ENT_QUOTES);
		$updateCount = htmlspecialchars($instance['count'], ENT_QUOTES);
		$showTweetTimeTF = $instance['showTweetTimeTF'];
		$widgetTitle = stripslashes(quot($instance['widgetTitle']));
		$includeRepliesTF = $instance['includeRepliesTF'];
	?>
		<p>
			<label for="<?php echo $this->get_field_id('user'); ?>" style="line-height:35px;display:block;">Twitter username: @<input type="text" size="12" id="<?php echo $this->get_field_id('user'); ?>" name="<?php echo $this->get_field_name('user'); ?>" value="<?php echo $username;?>" /></label>
			<label for="<?php echo $this->get_field_id('count'); ?>" style="line-height:35px;display:block;">Number of updates to show: <input type="text" id="<?php echo $this->get_field_id('count'); ?>" size="2" name="<?php echo $this->get_field_name('count'); ?>" value="<?php echo $updateCount; ?>" /></label>
			<label for="<?php echo $this->get_field_id('widgetTitle'); ?>" style="line-height:35px;display:block;">Title:<input type="text" id="<?php echo $this->get_field_id('widgetTitle'); ?>" size="16" name="<?php echo $this->get_field_name('widgetTitle'); ?>" value="<?php echo $widgetTitle; ?>" /></label>
			<p><input type="checkbox" id="<?php echo $this->get_field_id('showTweetTimeTF'); ?>" value="1" name="<?php echo $this->get_field_name('showTweetTimeTF'); ?>"<?php if($showTweetTimeTF){ ?> checked="checked"<?php } ?>> <label for="<?php echo $this->get_field_id('showTweetTimeTF'); ?>">Show tweeted "date and time"</label></p>
			<p><input type="checkbox" id="<?php echo $this->get_field_id('includeRepliesTF'); ?>" value="1" name="<?php echo $this->get_field_name('includeRepliesTF'); ?>"<?php if($includeRepliesTF){ ?> checked="checked"<?php } ?>> <label for="<?php echo $this->get_field_id('includeRepliesTF'); ?>">Include replies</label></p>
		</p>
<?php
	}

	function update($new_instance, $old_instance) {
		// processes widget options to be saved
		$instance = $old_instance;
		$instance['user'] = esc_html($new_instance['user']);
		$instance['count'] = esc_html($new_instance['count']);
		$instance['widgetTitle'] = esc_html( $new_instance['widgetTitle']);
		if( $new_instance['showTweetTimeTF']=="1"){
			$instance['showTweetTimeTF'] = true;
		} else{
			$instance['showTweetTimeTF'] = false;
		}
		if( $new_instance['includeRepliesTF']=="1"){
			$instance['includeRepliesTF'] = true;
		} else{
			$instance['includeRepliesTF'] = false;
		}
		return $instance;
	}

	function widget($args, $instance) {
		// outputs the content of the widget
		extract($args, EXTR_SKIP);
		//default to my twitter name
		$username = empty($instance['user']) ? "salzano" : $instance['user'];
		$updateCount = empty($instance['count']) ? 3 : $instance['count'];
		$showTweetTimeTF = $instance['showTweetTimeTF'];
		$title = $instance['widgetTitle'];
		$includeRepliesTF = $instance['includeRepliesTF'];

		$jsonFileName = "$username.json";
		$jsonTempFileName = "$username.json.tmp";
		$jsonURL = "http://api.twitter.com/1/statuses/user_timeline.json?screen_name=$username&include_entities=true";

		//have we fetched twitter data in the last half hour?
		if( $this->file_missing_or_old( $jsonFileName, .5 )){
			//get new data from twitter
			$jsonData = $this->save_remote_file( $jsonURL, $jsonFileName );
		} else{
			//already have file, get the data out of it
			$jsonData = $this->get_json_data_from_file( $jsonFileName );
		}

		// check for errors--rate limit or curl not installed
		// data returned will be: {"error":"Rate limit exceeded. Clients may not make more than 150 requests per hour.","request":"\/1\/statuses\/user_timeline.json?screen_name=salzano&include_entities=true"}

		if( $jsonData == "" || iconv_strlen( $tweets->error, "UTF-8" )){
			//delete the json file so it will surely be downloaded on next page view
			if( file_exists( dirname(__FILE__) ."/". $jsonFileName )){
				unlink( dirname(__FILE__) ."/". $jsonFileName );
			}
			//get the backup data
			$jsonData = $this->get_json_data_from_file( $jsonTempFileName );
		} else{
			//good file, create a backup
			if( file_exists( dirname(__FILE__) . "/" . $jsonFileName )){
				copy( dirname(__FILE__) . "/" . $jsonFileName, dirname(__FILE__) . "/" . $jsonTempFileName );
			}
		}

		if( $tweets = json_decode( $jsonData )){
			$haveTwitterData = true;
		} else{
			//tweets is null
			$haveTwitterData = false;
		}

		// output the widget
		$pluginURL = get_bloginfo('home')."/wp-content/plugins/latest-twitter-updates-with-date-and-time/";
		$icon2 = $pluginURL . "twitter_logo.gif";
		$title = empty($title) ? '&nbsp;' : apply_filters('widget_title', $title);
		echo $before_widget;
		if( !empty( $title ) && $title != "&nbsp;" ) { echo $before_title . $title . "<img id=\"latest-twitter-widget-icon2\" src=\"".$icon2."\" alt=\"t\"></a>" . $after_title ; };
		if( $haveTwitterData ){
			$linkHTML = "<a href=\"http://twitter.com/".$username."\">";
			$pluginURL = get_bloginfo('home')."/wp-content/plugins/latest-twitter-updates-with-date-and-time/";
			$i=1;
			foreach( $tweets as $tweet ){
				//exit this loop if we have reached updateCount
				if( $i > $updateCount ){ break; }
				//skip this iteration of the loop if this is a reply and we are not showing replies
				if( !$includeRepliesTF && strlen( $tweet->in_reply_to_screen_name )){ 		continue;	}
				echo "<div class=\"latest-twitter-tweet\">&quot;" . $this->fix_twitter_update( $tweet->text, $tweet->entities ) . "&quot;</div>";
				if( $showTweetTimeTF ){
					echo "<div class=\"latest-twitter-tweet-time\" id=\"latest-twitter-tweet-time-" . $i . "\">" . $this->twitter_time_ltw( $tweet->created_at) . "</div>";
				}
				$i++;
			}
		} else{
			echo "have data = false $jsonData";
		}
		//show this no matter what, tweets or no tweets
		echo "<div id=\"latest-twitter-follow-link\"><a class=\"twitter_link\" href=\"http://twitter.com/$username\">Follow Us</a></div>";
		echo $after_widget;
	}

	function fix_twitter_update($origTweet,$entities) {
		if( $entities == null ){ return $origTweet; }
		foreach( $entities->urls as $url ){
			$index[$url->indices[0]] = "<a class=\"twitter_link\" href=\"".$url->url."\">".$url->url."</a>";
			$endEntity[(int)$url->indices[0]] = (int)$url->indices[1];
		}
		foreach( $entities->hashtags as $hashtag ){
			$index[$hashtag->indices[0]] = "<a class=\"twitter_link\" href=\"http://twitter.com/#!/search?q=%23".$hashtag->text."\">#".$hashtag->text."</a>";
			$endEntity[$hashtag->indices[0]] = $hashtag->indices[1];
		}
		foreach( $entities->user_mentions as $user_mention ){
			$index[$user_mention->indices[0]] = "<a class=\"twitter_link\" href=\"http://twitter.com/".$user_mention->screen_name."\">@".$user_mention->screen_name."</a>";
			$endEntity[$user_mention->indices[0]] = $user_mention->indices[1];
		}
		$fixedTweet="";
		for($i=0;$i<iconv_strlen($origTweet, "UTF-8" );$i++){
			if(iconv_strlen($index[(int)$i], "UTF-8" )>0){
				$fixedTweet .= $index[(int)$i];
				$i = $endEntity[(int)$i]-1;
			} else{
				$fixedTweet .= iconv_substr( $origTweet,$i,1, "UTF-8" );
			}
		}
		return $fixedTweet;
	}
	
function twitter_time_ltw($a) {
	        //get timestamp when tweet created
	        
	        $b = strtotime($a);
			echo date('M j,Y  g:i A', $b);
	}
	
	function save_remote_file( $url, $fileName ){
		$response = wp_remote_get( $url );
		if( is_wp_error( $response ) || $response['response']['code'] != "200" ){
			//GET failed
			return false;
		} else{
			//save the body of the response in $fileName
			$filePath = dirname(__FILE__) ."/". $fileName;
			$fp = fopen( $filePath, "w");
			fwrite( $fp, $response['body'] );
			fclose( $fp );
			//that worked out well
			return $response['body'];
		}
	}

	function file_missing_or_old( $fileName, $ageInHours ){
		$fileName = dirname(__FILE__) ."/". $fileName;
		if( !file_exists( $fileName )){
			return true;
		} else{
			$fileModified = filemtime( $fileName );
			$today = time( );
			$hoursSince = round(($today - $fileModified)/3600, 3);
			if( $hoursSince > $ageInHours ){
				return true;
			} else{
				return false;
			}
		}
	}

	function get_json_data_from_file( $jsonFileName ){
		$fileName = dirname(__FILE__) ."/". $jsonFileName;
		$jsonData = "";
		if( file_exists( $fileName )){
			$theFile = fopen( $fileName, "r" );
			$jsonData = fread( $theFile, filesize( $fileName ));
			fclose( $theFile );
		}
		return $jsonData;
	}
}

if( !function_exists('register_latest_twitter_widget')){
	add_action('widgets_init', 'register_latest_twitter_widget');
	function register_latest_twitter_widget() {
	    register_widget('latest_twitter_widget');
	}
}

if( !function_exists('latest_twitter_widget_css')){
	function latest_twitter_widget_css( ){
		echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"" . get_bloginfo('wpurl') ."/wp-content/plugins/latest-twitter-updates-with-date-and-time/latest_twitter_updates.css\" />" . "\n";
	}
	add_action('wp_head', 'latest_twitter_widget_css');
}

?>
