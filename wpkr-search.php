<?php 
error_reporting(1);
require_once dirname(__FILE__)."/../../../wp-blog-header.php";

if (!((function_exists("current_user_can") && current_user_can('activate_plugins')) || $user_level > 5)) {
	echo __('Error: You are not authorized','wp_kradeno');
	exit;
}

if (get_option('wpkr_db_version')===false) {
	echo __('Error: The plugin should be activated first.','wp_kradeno');
	exit;
}

$table_name = $wpdb->prefix . "yuri_kradeno";

if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
	echo __('Error: There was a problem during the installation.','wp_kradeno');
	exit;
}


ini_set('pcre.backtrack_limit',500000);
ini_set('memory_limit',120000000);

$exclude_site = get_option('wpkr_exclude_sites');
$googlecalls = get_option('wpkr_googlecalls');
$wpkr_ratinglimit = get_option('wpkr_ratinglimit');

$wpkr_ignoreposts = get_option('wpkr_ignoreposts');
if (!is_array($wpkr_ignoreposts))
	$wpkr_ignoreposts=array();

if ($exclude_site===false)
	$exclude_site=array();

$posts=array();
$posts=get_posts(array('numberposts'=>-1));


if (count($posts)==0) {
	echo "Error: No posts found;";
       	exit;
}

$progress=0;
$sites_new=0;
$skipped=0;

if (get_option('wpkr_lastcheck')==false)
	add_option('wpkr_lastcheck',time(),'','yes');
else
	update_option('wpkr_lastcheck',time());


$temp_posts=array();

foreach( $posts as $post ) {
	if (in_array($post->ID, $wpkr_ignoreposts))
		continue;	

	if (isset($_GET['last3']) && strtotime($post->post_date)<=strtotime('-3 months'))
		continue;

	$temp_posts[]=$post;
}
$posts=$temp_posts;

echo "|progress:".(count($posts)*10);

foreach( $posts as $post ) {
	
	echo "\n$progress|$skipped|$sites_new|progress";
	set_time_limit(180);

	$lastchecked=get_post_meta($post->ID,'_wpkr_lastchecked',true);
	if ($lastchecked!="" && intval($lastchecked)+3*24*3600>time() && !isset($_GET['force'])) {
		$skipped++;
		$progress+=10;
		continue;
	}

	if ($googlecalls<=10) {
		echo "\n".__('Google calls limit reached','wp_kradeno').'. '.__('Progress','wp_kradeno').': '.(100*$progress/(count($posts)*10)).'% '.__('Sites found','wp_kradeno').': '.$sites_new;
		exit;
	}

	$sites =getPostCopies($post, $exclude_site);	

	if ($sites) {
		$existing_data=array();
		$yuri_query="select visibleUrl, rating, status from $table_name where post_id=".$post->ID;
		$yuri_query_res = mysql_query($yuri_query);
		if ($yuri_query_res)
			while ($row = mysql_fetch_assoc($yuri_query_res))
				$existing_data[$row['visibleUrl']]=array($row['rating'],$row['status']);
		mysql_free_result($yuri_query_res);

		$subprogress=$progress;
		foreach ($sites as $site) {
			$subprogress+=10/count($sites);
			echo "\n$subprogress|$skipped|$sites_new|progress";

			if (floatval($site->rating[0])/floatval($site->rating[1])<floatval($wpkr_ratinglimit))
				continue;

			if (isset($existing_data[$site->visibleUrl])) {

				if ($existing_data[$site->visibleUrl][1]==1 || 
 					$existing_data[$site->visibleUrl][1]==3 ||
					$existing_data[$site->visibleUrl][1]==4)
					continue;

				$oldrating=explode("/",$existing_data[$site->visibleUrl][0]);
				$newrating=array(intval($oldrating[0])+$site->rating[0],intval($oldrating[1])+$site->rating[1]);
				if ($newrating[0]>9999) $newrating[0]=9999;
				if ($newrating[1]>9999) $newrating[1]=9999;
				$newrating=$newrating[0]."/".$newrating[1];
				$yuri_query = "UPDATE $table_name SET rating='".$newrating."' WHERE post_id='".$post->ID."' and visibleUrl='".$site->visibleUrl."' limit 1";
			} else {
				$sites_new++;
				$newrating=$site->rating[0]."/".$site->rating[1];
				$yuri_query = "INSERT INTO $table_name VALUES ('".$post->ID."','".$site->visibleUrl."','".$site->unescapedUrl."','".$site->cacheUrl."','".$site->titleNoFormatting."',0,'".$newrating."')";
			}
			mysql_unbuffered_query($yuri_query);

//			echo $site->rating[0].'/'.$site->rating[1].' <a href="'.$site->unescapedUrl.'">'.$site->visibleUrl."</a> <a href='".$site->cacheUrl."'>cache</a><br>";

		}
	}

	unset($sites);
	$progress+=10;

	if ($lastchecked=='') 
		add_post_meta($post->ID,'_wpkr_lastchecked',time(), true);	
	else
		update_post_meta($post->ID,'_wpkr_lastchecked',time());	
}	

echo "\n".__('Finished','wp_kradeno').'. '.__('Progress','wp_kradeno').': '.(100*$progress/(count($posts)*10)).'% '.__('Sites found','wp_kradeno').': '.$sites_new;


function getPostCopies($post, $exclude_site) {
	$text=$post->post_content;
	if (strpos($text,'<!--more-->')!==false)
		$text=substr(strstr($text,'<!--more-->'),11);
	$terms=extract_terms1($text);
	if ($terms!==false) 
		return getMentions($terms,$exclude_site);
	else 
		return false;
}

function getMentions($terms, $exclude_site) {
	$res=array();
	foreach ($terms as $term) {
		$query= "\"$term\" -site:".implode(' -site:',$exclude_site);

		$resnewall= getSearchRes($query);
		$resnew=array();
		foreach ($resnewall as $result) 
			if (!in_array($result->visibleUrl,array_keys($resnew)) && !in_array($result->visibleUrl,$exclude_site))
				$resnew[$result->visibleUrl]=$result;
		foreach ($resnew as $key=>$result) 
			if (!in_array($key,array_keys($res))) {
				$result->rating=1;
				$res[$key]=$result;
				
			} else
				$res[$key]->rating++;
	}
	foreach ($res as $key=>$result) 
		$res[$key]->rating=array($res[$key]->rating,count($terms));
	return $res;	
}

function getSearchRes($query, $page=0) {
	global $googlecalls;
	$googlecalls--;
	$url = "http://ajax.googleapis.com/ajax/services/search/web?v=1.0&"
	    . "q=".urlencode($query)."&key=ABQIAAAAwN54SCg_54JWJH1ji7ZyXhT_MEXmrUi-QvahWM-IwoaOoFl90xQKAVwre55zSY5smsYsUiHquZ0-rw&start=".($page*8)."&rsz=large&filter=0&userip=".$_SERVER['REMOTE_ADDR'];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_REFERER, get_option('siteurl'));
	$body = curl_exec($ch);
	curl_close($ch);
	$json = json_decode($body);
	if ($json==null || !isset($json->responseData) || !isset($json->responseData->results))
		return array();
	$res=$json->responseData->results;
	if (!is_array($res))
		return array();
	
	if ($json->responseData->cursor->estimatedResultCount>=8 && count($json->responseData->results)==8 && $page<=50) {
		$temp=getSearchRes($query,$page+1);
		$res=array_merge($res,$temp);
	}	

	return $res;
}

function extract_terms1($document){
	$search = array('@<script[^>]*?>.*?</script>@si', 
		       '@<[\/\!]*?[^<>]*?>@si',           
		       '@<style[^>]*?>.*?</style>@siU',   
		       '@<![\s\S]*?--[ \t\n\r]*>@' ,
			'@(\|(\s+)?)+@'
	);
	$text = preg_replace($search, '', $document);
	$termcount = floor(count(explode(' ',$text))/100);
	if ($termcount<6) $termcount=6;
	if ($termcount>9) $termcount=9;
	$res=array();
	if (preg_match_all('@(?:(?:[^\s\|]+\s){0,'.rand(1,10).'}?)((?:(?:[^\s\|]{1,2}\s)?[^\s\|]{3,500}\s?){'.$termcount.'})@u', $text, &$match, PREG_PATTERN_ORDER)!==false)
		foreach ($match[1] as $onematch)
			$res[]=str_replace('"','\"',trim($onematch));
	if (count($res)==0)
		return false;
	$res=array_slice($res,0,10);
	return $res;
} 

/*
function extract_terms($document){
	$search = array('@<script[^>]*?>.*?</script>@si', 
		       '@<[\/\!]*?[^<>]*?>@si',           
		       '@<style[^>]*?>.*?</style>@siU',   
		       '@<![\s\S]*?--[ \t\n\r]*>@' ,
			'@(\|(\s+)?)+@'
	);
	$text = preg_replace($search, '|', $document);
	$text = preg_replace('@(^|\|)([^\s\|]{1,2}\s)*([^\s\|]{3,500}(\s[^\s\|]{1,2})*?\s?){0,6}?(?=$|\|)@u', '', $text);
	$res=array();
	if (preg_match_all('@(?:\|(?:[^\s\|]+\s){0,'.rand(1,20).'}?)((?:(?:[^\s\|]{1,2}\s)?[^\s\|]{3,500}\s?){7})@u', $text, &$match, PREG_PATTERN_ORDER)!==false)
		foreach ($match[1] as $onematch)
			$res[]=addslashes(trim($onematch));
	if (count($res)==0)
		return false;
	$res=array_slice($res,0,10);
	return $res;
} */

?>
