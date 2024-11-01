<?php
/*
Plugin Name: WP Kradeno
Plugin URI: http://yurukov.net/
Description: Searches the web for stollen blog posts.
Version: 0.7
Author: Boyan Yurukov
Author URI: http://yurukov.net/blog

*/
$wpkr_db_version="0.7";
$wpkr_table_name = $wpdb->prefix . "yuri_kradeno";
$wpkr_exclude_sites = get_option('wpkr_exclude_sites');
$wpkr_googlecalls = get_option('wpkr_googlecalls');
$wpkr_ratinglimit = get_option('wpkr_ratinglimit');
$wpkr_lastcheck = get_option('wpkr_lastcheck');
$wpkr_recheckdays = get_option('wpkr_recheckdays');
$wpkr_ignoreposts = get_option('wpkr_ignoreposts');
$wpkr_flash="";

function wpkr_install() {
	global $wpdb, $wpkr_db_version;
	
	$wpkr_table_name = $wpdb->prefix . "yuri_kradeno";
	$sql = "CREATE TABLE IF NOT EXISTS " . $wpkr_table_name . " (
		`post_id` BIGINT NOT NULL ,
		`visibleUrl` VARCHAR( 150 ) NOT NULL ,
		`url` VARCHAR( 600 ) NOT NULL ,
		`cacheUrl` VARCHAR( 700 ) NOT NULL ,
		`title` VARCHAR( 600 ) NOT NULL ,
		`status` INT NOT NULL ,
		`rating` CHAR(9) NOT NULL,
		PRIMARY KEY ( `post_id` , `visibleUrl` ) 
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	if($wpdb->get_var("show tables like '$wpkr_table_name'") != $wpkr_table_name) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	if($wpdb->get_var("show tables like '$wpkr_table_name'") != $wpkr_table_name) {
		$result = $wpdb->query($sql);
	}

	add_option('wpkr_exclude_sites',array(parse_url(get_option('siteurl'),PHP_URL_HOST)),'','yes');
	add_option('wpkr_googlecalls',4000,'','yes');
	add_option('wpkr_ratinglimit','0.5','','yes');
	add_option('wpkr_recheckdays',30,'','yes');
	add_option('wpkr_ignoreposts','','','yes');
	add_option("wpkr_db_version", $wpkr_db_version);

}

function wpkr_uninstall() {
	delete_option('wpkr_db_version');
}

function wpkr_is_authorized() {
	global $user_level;
	if (function_exists("current_user_can")) {
		return current_user_can('activate_plugins');
	} else {
		return $user_level > 5;
	}
}
function wpkr_generate_hash() {
	return md5(uniqid(rand(), TRUE));
}
function wpkr_is_hash_valid($form_hash) {
	$saved_hash = wpkr_retrieve_hash();
	return $form_hash === $saved_hash;
}
function wpkr_store_hash($generated_hash) {
	return update_option('wpkr_token',$generated_hash);
}
function wpkr_retrieve_hash() {
	return get_option('wpkr_token');
}

function wpkr_flash_clear() {
	global $wpkr_flash;
	$wpkr_flash="";
}

function wpkr_flash_add($message,$error=null) {
	global $wpkr_flash;
	$wpkr_flash .= ($wpkr_flash!=''?"<br/>":"");
	$wpkr_flash .= ($error?"<span style='color:red'>":"").$message.($error?"</span>":"");
}


function wpkr_report_subpanel() {
	global $wpkr_exclude_sites, $_POST, $wp_rewrite, $wpdb, $wpkr_flash, $wpkr_table_name, $wpkr_lastcheck, $wpkr_recheckdays, $wpkr_ignoreposts;

	//status 0.new 1.ignore 2.warned 3.successful 4.agreed

	if (wpkr_is_authorized() && wpkr_is_hash_valid($_POST['token']) && strpos($_POST['site'],"'")===false && strpos($_POST['site'],'"')===false) {
		wpkr_flash_clear();
	
		$site_temp=explode(',',$_POST['site']);
		$site=array();
		foreach($site_temp as $site_one)
			$site[]=urlencode($site_one);
			

		if ($_POST['wpkr_action']=='stop_checks' && isset($_POST['post_id']) && intval($_POST['post_id'])!=0) { 
			$yuri_query = "DELETE FROM $wpkr_table_name WHERE post_id='".intval($_POST['post_id'])."'";
			mysql_query($yuri_query);
			$wpkr_ignoreposts[]=$_POST['post_id'];
			update_option('wpkr_ignoreposts',$wpkr_ignoreposts);
		} else
		if ($_POST['wpkr_action']=='exclude_all') { 
			$yuri_query = "DELETE FROM $wpkr_table_name WHERE visibleUrl in ('".implode("','",$site)."')";
			mysql_query($yuri_query);
	
			if (count(array_intersect($site,$wpkr_exclude_sites))>=count($site))
				wpkr_flash_add(implode(',',$site).' '.__("already excluded globally.",'wp_kradeno'),'error');
			else {			
				$wpkr_exclude_sites=array_merge($wpkr_exclude_sites,$site);
				update_option('wpkr_exclude_sites',$wpkr_exclude_sites);
				
				wpkr_flash_add(implode(',',$site).' '.__("excluded globally.",'wp_kradeno'));
			}
		} else

		if ($_POST['wpkr_action']=='ignore_site' && isset($_POST['post_id']) && intval($_POST['post_id'])!=0) { 
			$yuri_query = "UPDATE $wpkr_table_name SET status='1' WHERE post_id='".intval($_POST['post_id'])."' and visibleUrl in ('".implode("','",$site)."') limit ".count($site);
			mysql_query($yuri_query);

			wpkr_flash_add(implode(',',$site).' '.__("ignored for one post.",'wp_kradeno'));
		} else

		if ($_POST['wpkr_action']=='warned_site' && isset($_POST['post_id']) && intval($_POST['post_id'])!=0) { 
			$yuri_query = "UPDATE $wpkr_table_name SET status='2' WHERE post_id='".intval($_POST['post_id'])."' and visibleUrl in ('".implode("','",$site)."') limit ".count($site);
			mysql_query($yuri_query);

			wpkr_flash_add(implode(',',$site).' '.__("set warned for one post.",'wp_kradeno'));
		} else

		if ($_POST['wpkr_action']=='successful_site' && isset($_POST['post_id']) && intval($_POST['post_id'])!=0) { 
			$yuri_query = "UPDATE $wpkr_table_name SET status='3' WHERE post_id='".intval($_POST['post_id'])."' and visibleUrl in ('".implode("','",$site)."') limit ".count($site);
			mysql_query($yuri_query);

			wpkr_flash_add(implode(',',$site).' '.__("set successfully changed for one post.",'wp_kradeno'));
		} else
			
		if ($_POST['wpkr_action']=='agreed_site' && isset($_POST['post_id']) && intval($_POST['post_id'])!=0) { 
			$yuri_query = "UPDATE $wpkr_table_name SET status='4' WHERE post_id='".intval($_POST['post_id'])."' and visibleUrl in ('".implode("','",$site)."') limit ".count($site);
			mysql_query($yuri_query);

			wpkr_flash_add(implode(',',$site).' '.__("set agreed for one post.",'wp_kradeno'));
		}

	}

	if ($wpkr_flash != '') echo '<div id="message" class="updated fade"><p>' . $wpkr_flash . '</p></div>';

	if (wpkr_is_authorized()) {
		$temp_hash = wpkr_generate_hash();
		wpkr_store_hash($temp_hash);


		$yuri_query="select * from $wpkr_table_name where not status in (1,3,4) order by post_id desc";
		$yuri_query_res = mysql_query($yuri_query);

		$post_kradeni=array();
		if ($yuri_query_res)
			while ($row = mysql_fetch_assoc($yuri_query_res)) {
				$rating=explode("/",$row['rating']);
				$rating=strval(intval(round(100*floatval($rating[0])/floatval($rating[1]))));
				$row['rating']=$rating;
				$rating=sprintf('%.02f',floatval($rating)/100).$row['visibleUrl'];
				if (isset($post_kradeni[$row['post_id']]))
					$post_kradeni[$row['post_id']][$rating]=$row;
				else			
					$post_kradeni[$row['post_id']]=array($rating=>$row);
			}

		echo '<div class="wrap">
		<h2>'.__("WP Kradeno Report",'wp_kradeno').'</h2>


		<script>
			wpkg_checkStats_handle=false;
			function wpkg_batch_sites_submit(obj) {
				var site="";
				for (i=0;i<obj.elements.length;i++)
					if (obj.elements[i].name=="batch_site" && obj.elements[i].checked!=false && obj.elements[i].checked!=\'false\')
						site+=","+obj.elements[i].value;
				obj.site.value=site.substr(1);
				if (site!="" && (!wpkg_checkStats_handle || 
					(wpkg_checkStats_handle && confirm("'.__('This action will stop the checking process. Are you sure?','wp_kradeno').'")))) {
					wpkg_stopCheck(); 
					return true;
				} else
					return false;
			}
			function wpkg_batch_sites_checkall(obj) {
				for (i=0;i<obj.form.elements.length;i++)
					if (obj.form.elements[i].name=="batch_site")
						obj.form.elements[i].checked=obj.checked;
			}	
			function wpkg_startCheck() {
				var forced= document.getElementById("wpkr_checkpanel_force").checked!=false;
				var last3= document.getElementById("wpkr_checkpanel_last3").checked!=false;
				var bar1=document.getElementById("wpkr_checkpanel_bar1");
				var bar2=document.getElementById("wpkr_checkpanel_bar2");
				var info=document.getElementById("wpkr_checkpanel_info");
				var frame=document.getElementById("wpkr_checkpanel_frame");
				bar1.style.width="0%";			
				bar2.style.width="80%";	
				info.innerHTML="'.__('Starting...','wp_kradeno').'";
				wpkg_checkStats_handle=setInterval(wpkg_checkStats,500);
				frame.src="'.WP_PLUGIN_URL.'/wp-kradeno/wpkr-search.php"+(forced?"?force":"") + (last3? (forced?"&":"?") + "last3" : "");
				this.parentNode.innerHTML="'.__('Stand by...','wp_kradeno').' <a href=\'javascript:;\' onclick=\'wpkg_stopCheck();\'>'.__('or stop','wp_kradeno').'</a>.";
			}
			function wpkg_checkStats() {
				var bar1=document.getElementById("wpkr_checkpanel_bar1");
				var bar2=document.getElementById("wpkr_checkpanel_bar2");
				var info=document.getElementById("wpkr_checkpanel_info");
				var frame=document.getElementById("wpkr_checkpanel_frame");
				text=frame.contentDocument.body.innerHTML;
				allprogress=parseFloat(text.substring(text.indexOf(":")+1,text.indexOf("\n")));
				status=text.substring(text.lastIndexOf("\n")+1);
				
				if (status.indexOf("%")!=-1) {
					if (status.indexOf("100%")!=-1) {
						bar1.style.width="80%";
						bar2.style.width="0%";
					}
					wpkg_stopCheck();
					info.innerHTML=status;
				} else {
					status=status.split("|");
					if (status.length>=4 && !isNaN(parseFloat(status[0])) && !isNaN(allprogress)) {
						info.innerHTML="'.__('Progress','wp_kradeno').': "+(Math.round(10000*parseFloat(status[0])/allprogress)/100)+"%; '.__('Skipped posts','wp_kradeno').':"+status[1]+"; '.__('Found new reposts','wp_kradeno').': "+status[2];

						bar1.style.width=(80*parseFloat(status[0])/allprogress)+"%";
						bar2.style.width=(80*(1-parseFloat(status[0])/allprogress))+"%";
					}
				}
			}
			function wpkg_stopCheck() {
				if (wpkg_checkStats_handle) {
					clearInterval(wpkg_checkStats_handle);
					var frame=document.getElementById("wpkr_checkpanel_frame");
					var controls=document.getElementById("wpkr_checkpanel_controls");
					controls.innerHTML="<span style=\'color:red\'>'.__('Refresh recommended','wp_kradeno').'. <a href=\'javascript:;\' onclick=\'location.reload(true);\'>'.__('refresh','wp_kradeno').'</a></span>";
					frame.src="";
					wpkg_checkStats_handle=false;
				}
			}

		</script>


		<div style="margin:10px 0 10px 20px;border:1px solid #aaa; width:95%"><div style="padding:10px;">';
		
		if ($wpkr_lastcheck) {
			$notcheckedfor=intval(round((time()-$wpkr_lastcheck)/3600/24));
			if ($notcheckedfor>$wpkr_recheckdays)
				echo '<span style="color:red">'.sprintf(__("You haven't checked for reposts for %d days",'wp_kradeno'),$notcheckedfor).'</span> ';
		} else
			echo '<span style="color:red">'.__("You haven't checked for reposts so far.",'wp_kradeno').'</span> ';
	
		echo __("You could start a report check here. Note that it may take at least 10 minutes. This depends on the number of posts you have, their size and on the speed of your server. If you have a lot of posts, you may run out of Google API calls (how many requests it's save to send to Google). In this case, you could run the check an hour later and it will continue from where it ended. The checking algorithm will skip all posts that were checked in the last 6 hours. You may override this by marking the force checkbox.",'wp_kradeno').'
		<br><br>
		<div id="wpkr_checkpanel_controls" ><input type="button" value="'.__("Start check",'wp_kradeno').'" onclick="wpkg_startCheck.call(this);"/><br/>
		<input type="checkbox" id="wpkr_checkpanel_last3" checked="true"><label for="wpkr_checkpanel_last3">'.__("Check only the posts in the last 3 months",'wp_kradeno').'</label><br/>
		<input type="checkbox" id="wpkr_checkpanel_force"><label for="wpkr_checkpanel_force">'.__("Force check posts regardless when they were last checked",'wp_kradeno').'</label></div>
		<div id="wpkr_checkpanel_bar1" style="background:green;color:green;border-top:1px solid gray;border-left:1px solid gray;border-bottom:1px solid gray;float:left;margin:4px 0; width:0%;height:20px;">.</div>
		<div id="wpkr_checkpanel_bar2" style="background:white;color:white;border-top:1px solid gray;border-right:1px solid gray;border-bottom:1px solid gray;float:left;margin:4px 0; width:80%;height:20px;">.</div>
		<div id="wpkr_checkpanel_info" style="clear:both;"></div>
		<iframe id="wpkr_checkpanel_frame" frameborder="0" style="display:none;width:10px;height:10px;" src="javascript:;"></iframe>
		</div></div>';


		if (count($post_kradeni)>0) 
			foreach ($post_kradeni as $post_id=>$sites) {
				$post=get_post($post_id);
				echo '<br><h3 style="display:inline">'.$post->post_title.'</h3> <a href="'.get_permalink($post_id).'" target="_blank">open</a><br>';
		
				$sortedkeys=array_keys($sites);	
				sort($sortedkeys, SORT_STRING);
				$sortedkeys=array_reverse($sortedkeys);	

				echo '<form action="" method="post" onsubmit="return wpkg_batch_sites_submit(this);">
		<input type="hidden" name="redirect" value="true" />
		<input type="hidden" name="token" value="' . wpkr_retrieve_hash() . '" />
		<input type="hidden" name="post_id" value="'.$post_id.'"/>
		<input type="hidden" name="site" value=""/>

		<table style="margin:10px 0 10px 20px; border:1px solid #aaa; width:95%" cellspacing="5px">';

				foreach( $sortedkeys as $key) {
					echo '<tr>
	<td style="width:30px;"><input type="checkbox" name="batch_site" value="'.$sites[$key]['visibleUrl'].'" '.(count($sortedkeys)==1?"disabled='true' checked='true'":"").'/></td> 
	<td style="color:#888; width:45px;">'.$sites[$key]['rating'].'%</td> 
	<td style="width:300px;">'.$sites[$key]['visibleUrl'].'
	<a href="'.$sites[$key]['url'].'">visit</a> <a href="'.$sites[$key]['cacheUrl'].'">cache</a>'.
	($sites[$key]['status']==2?' <span style="color:red">'.__('warned','wp_kradeno').'</span>':'').'</td>
	<td style="color:#aaa">'.$sites[$key]['title'].'</td>
	</tr>';
				}
		
			echo '
	<tr><td style="border-top:1px solid #aaa;padding-top:5px;" colspan="3"><input type="checkbox" onclick="wpkg_batch_sites_checkall(this);" '.(count($sortedkeys)==1?"disabled='true'":"").'/>
	<select name="wpkr_action">
		<option disabled="true" selected >'.__('Select action...','wp_kradeno').'</option>
		<option value="ignore_site">'.__('Ignore for this post','wp_kradeno').'</option>
		<option value="warned_site">'.__('Warning sent','wp_kradeno').'</option>
		<option value="successful_site">'.__('Successfully changed','wp_kradeno').'</option>
		<option value="agreed_site">'.__('Agreed to repost','wp_kradeno').'</option>
		<option disabled="true"> </option>
		<option value="stop_checks">'.__('Stop checks for this post','wp_kradeno').'</option>
		<option value="exclude_all">'.__('Exclude for all','wp_kradeno').'</option>
	</select>
	<input type="submit" value="'.__('Apply','wp_kradeno').'"/>
	</td><td><span style="visibility:hidden">.</span></td>
	</tr>
	</table>
	</form>';
			}

		echo '</div>';
	} else {
		echo '<div class="wrap"><p>'.__('Sorry, you are not allowed to access this page.','wp_kradeno').'</p></div>';
	}

}


function wpkr_options_subpanel() {
	global $wpkr_exclude_sites, $wpkr_recheckperiod, $wpkr_googlecalls, $wpkr_ratinglimit, $_POST, $wp_rewrite, $wpdb, $wpkr_flash, $wpkr_table_name, $wpkr_recheckdays, $wpkr_ignoreposts;

	if (wpkr_is_authorized()) {
		wpkr_flash_clear();
	
		if (!isset($_POST['restore_to_defaults'])) {
			if (isset($_POST['exclude_sites'])) {
				$temp=explode(',',$_POST['exclude_sites']);
				$temp1=array();
				foreach ($temp as $temp2) 
					if (strpos($temp2,"'")===false && strpos($temp2,'"')===false && strpos(trim($temp2)," ")===false)
						$temp1[]=urlencode(trim($temp2));
				if (count($temp1)==0) {
					update_option('wpkr_exclude_sites',$wpkr_exclude_sites=array(parse_url(get_option('siteurl'),PHP_URL_HOST)));
					wpkr_flash_add(__("Excluded sites updated.",'wp_kradeno'));
				} else
				if ($temp1!=$wpkr_exclude_sites) {
					$wpkr_exclude_sites=$temp1;
					update_option('wpkr_exclude_sites',$wpkr_exclude_sites);
					wpkr_flash_add(__("Excluded sites updated.",'wp_kradeno'));
				}
			}
			if (isset($_POST['googlecalls'])) {
				$temp=intval($_POST['googlecalls']);
				if ($temp>100 && $wpkr_googlecalls!=$temp) {
					$wpkr_googlecalls=$temp;
					update_option('wpkr_googlecalls',$wpkr_googlecalls);
					wpkr_flash_add(__("Google calls limit updated.",'wp_kradeno'));
				}
			}
			if (isset($_POST['ratinglimit'])) {
				$temp=floatval(str_replace(',','.',$_POST['ratinglimit']));
				if ($temp>0.2 && $temp<1.0 && $wpkr_ratinglimit!=$temp) {
					$wpkr_ratinglimit=$temp;
					update_option('wpkr_ratinglimit',$wpkr_ratinglimit);
					wpkr_flash_add(__("Rating limit updated.",'wp_kradeno'));
				}
			}
			if (isset($_POST['recheckdays'])) {
				$temp=intval($_POST['recheckdays']);
				if ($temp>=3 && $wpkr_recheckdays!=$temp) {
					$wpkr_recheckdays=$temp;
					update_option('wpkr_recheckdays',$wpkr_recheckdays);
					wpkr_flash_add(__("Recheck alert days updated.",'wp_kradeno'));
				}
			}
			if (isset($_POST['ignoreposts'])) {
				$temp=$_POST['ignoreposts'];
				$temp=explode(',',$_POST['ignoreposts']);
				$temp1=array();
				foreach ($temp as $temp2) 
					if (intval(trim($temp2))!==false && intval(trim($temp2))!=0)
						$temp1[]=intval(trim($temp2));
				if ($wpkr_ignoreposts!=$temp1) {
					$wpkr_ignoreposts=$temp1;
					update_option('wpkr_ignoreposts',$wpkr_ignoreposts);
					wpkr_flash_add(__("Ignored posts ids updated.",'wp_kradeno'));
				}
			}

				
		} else {
			update_option('wpkr_exclude_sites',$wpkr_exclude_sites=array(parse_url(get_option('siteurl'),PHP_URL_HOST)));
			update_option('wpkr_recheckperiod',$wpkr_recheckperiod=14);
			update_option('wpkr_googlecalls',$wpkr_googlecalls=2000);
			update_option('wpkr_ratinglimit',$wpkr_ratinglimit='0.5');
			update_option('wpkr_ignoreposts',$wpkr_ignoreposts='');
		}
	}

	if ($wpkr_flash != '') echo '<div id="message" class="updated fade"><p>' . $wpkr_flash . '</p></div>';

	if (wpkr_is_authorized()) {
		
		echo '<div class="wrap">
		<h2>'.__("WP Kradeno Settings",'wp_kradeno').'</h2>
		<div style="margin:10px 0 10px 20px;border:1px solid #aaa; width:70%"><div style="padding:10px;">
		'.sprintf(__("You can check for new reposts %shere%s.",'wp_kradeno'),'<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">','</a>').'
		</div></div>

		<form action="" method="post">
		<input type="hidden" name="redirect" value="true" />
		<input type="hidden" name="token" value="' . wpkr_retrieve_hash() . '" />

		<table class="form-table">
		<tr valign="top">
		<th scope="row">'.__("Excluded sites",'wp_kradeno').'</th>
		<td>
		<p><textarea cols="80" rows="10" name="exclude_sites">'.implode(", ",$wpkr_exclude_sites).'</textarea></p>
		<p><i>'.__("Comma separated. Include only the host name - without 'http', any slashes or quotes.",'wp_kradeno').'</i></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">'.__("Google calls limit",'wp_kradeno').'</th>
		<td>
		<p><input type="text" size=20 name="googlecalls" value="'.$wpkr_googlecalls.'"></p>
		<p><i>'.__("Please enter a number >100. This is the maximum number of request to Google Search per hour/session.",'wp_kradeno').' <span style="color:red">'.__("Warning",'wp_kradeno').':</span> '.__("do not change if you aren't sure what you are doing",'wp_kradeno').'.</i></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">'.__("Minimum search result rating",'wp_kradeno').'</th>
		<td>
		<p><input type="text" size=20 name="ratinglimit" value="'.$wpkr_ratinglimit.'"></p>
		<p><i>'.__("Please enter a number between 0.2 and 1.0. This is minimum average score accross multiple searches that a site should pass in order to be considered a repost.",'wp_kradeno').'</i></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">'.__("Alert to recheck after days",'wp_kradeno').'</th>
		<td>
		<p><input type="text" size=20 name="recheckdays" value="'.$wpkr_recheckdays.'"></p>
		<p><i>'.__("Please enter a number greater than 3. This will be the number of days after you'll be alerted to check for reposts.",'wp_kradeno').'</i></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">'.__("Excluded posts",'wp_kradeno').'</th>
		<td>
		<p><textarea cols="80" rows="3" name="ignoreposts">'.implode(", ",$wpkr_ignoreposts).'</textarea></p>
		<p><i>'.__("Comma separated. These are the ids of the posts that will not be checked.",'wp_kradeno').'</i></p>
		</td>
		</tr>
		</table>
		<p class="submit"><input class="button-primary" type="submit" value="'.__("Save",'wp_kradeno').'" /></p></form>
		<p>'.__("Restore settings to default.",'wp_kradeno').' <i>'.__("This will undo all your changes!",'wp_kradeno').'</i></p>
		<form action="" method="post">
		<input type="hidden" name="redirect" value="true" />
		<input type="hidden" name="token" value="' . wpkr_retrieve_hash() . '" />
		<input type="hidden" name="restore_to_defaults" value="true"/>
		<p class="submit"><input class="button-primary" type="submit" value="'.__("Restore to defaults",'wp_kradeno').'" /></p></form>		
		</form>';

		echo '</div>';
	} else {
		echo '<div class="wrap"><p>'.__('Sorry, you are not allowed to access this page.','wp_kradeno').'</p></div>';
	}

}

function wpkr_add_admin_pages() {
	if (function_exists('add_options_page')) {
		add_options_page('WP Kradeno', __('WP Kradeno','wp_kradeno'), 8, basename(__FILE__), 'wpkr_options_subpanel');
	}
	if (function_exists('add_posts_page')) {
		add_posts_page('WP Kradeno', __('WP Kradeno Reports','wp_kradeno'), 8, basename(__FILE__), 'wpkr_report_subpanel');
	}
}

function wpkr_rightnow() {
	global $wpkr_table_name, $wpkr_lastcheck, $wpkr_recheckdays;

	echo '<p>'.__('WP Kradeno:','wp_kradeno');

	$aretheremessages=true;
	if ($wpkr_lastcheck) {
		$notcheckedfor=intval(round((time()-$wpkr_lastcheck)/3600/24));
		if ($notcheckedfor>$wpkr_recheckdays)
			echo ' <span style="color:red">'.sprintf(__("You haven't checked for %s reposts%s for %d days",'wp_kradeno'),'<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">','</a>',$notcheckedfor).'</span>';
		else 
			$aretheremessages=false;
	} else
		echo ' <span style="color:red">'.sprintf(__("You haven't checked for %s reposts%s so far.",'wp_kradeno'),'<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">','</a>').'</span>';

	$yuri_query="select count(*) cnt, status from $wpkr_table_name where status in (0,2) group by status order by status asc";
	$yuri_query_res = mysql_query($yuri_query);

	if ($yuri_query_res && ($row1=mysql_fetch_assoc($yuri_query_res))) {
		$newrep=0;
		$repost=0;
		if (mysql_num_rows($yuri_query_res)==2 && ($row2=mysql_fetch_assoc($yuri_query_res))) {
			$newrep=$row1['cnt'];
			$repost=$row2['cnt'];
		} else {
			if ($row1['status']==0)
				$newrep=$row1['cnt'];
			else
				$repost=$row1['cnt'];
		}
		
		$temp=array();
		if ($newrep>0)
			$temp[]='<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">'.sprintf(__('%d possible re-posts','wp_kradeno'),$newrep).'</a>';
		if ($repost>0)
			$temp[]='<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">'.sprintf(__(' %d re-posts','wp_kradeno'),$repost).'</a> '.__('with preding warnings','wp_kradeno');

		echo ' '.implode(' '.__('and','wp_kradeno').' ',$temp).".";
		$aretheremessages=true;
	}

	if (!$aretheremessages)
		echo ' '.sprintf(__("No messages. You haven't checked for %s reposts%s though.",'wp_kradeno'),'<a href="'.clean_url("edit.php?page=wp-kradeno.php").'">','</a>');
	echo "</p>\n";
}

function wpkr_loadmo() {
	$currentLocale = get_locale();
	if(!empty($currentLocale)) 
		$currentLocale="bg_BG";
	$moFile = dirname(__FILE__) . "/lang/wpkr_" . $currentLocale . ".mo";
	if(@file_exists($moFile) && is_readable($moFile))
		load_textdomain('wp_kradeno', $moFile);
}	

register_activation_hook(__FILE__,'wpkr_install');
register_deactivation_hook(__FILE__,'wpkr_uninstall');
add_action('admin_menu', 'wpkr_add_admin_pages');
add_action('rightnow_end', 'wpkr_rightnow');
wpkr_loadmo();
?>
