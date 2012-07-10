<?php
/**
 * @package TinyPortal
 * @version 1.0
 * @author IchBin - http://www.tinyportal.net
 * @founder Bloc
 * @license MPL 2.0
 *
 * The contents of this file are subject to the Mozilla Public License Version 2.0
 * (the "License"); you may not use this package except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Copyright (C) 2012 - The TinyPortal Team
 *
 */

if (!defined('SMF'))
	die('Hacking attempt...');
	
global $context, $scripturl, $txt, $user_info, $settings, $smcFunc, $modSettings, $options;

if(loadLanguage('TPShout') == false)
	loadLanguage('TPShout', 'english');

// set version for databse updates.
$shoutboxversion = '101';

$shoutboxtemplate = '111';

// check if it needs updating...
if((isset($context['TPortal']['shoutbox_version']) && $shoutboxversion != $context['TPortal']['shoutbox_version']) || !isset($context['TPortal']['shoutbox_version']))
	shoutbox_update();

// bbc code for shoutbox
$context['html_headers'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		var tp_images_url = "' .$settings['tp_images_url'] . '";
		var tp_session_id = "' . $context['session_id'] . '";
		var tp_session_var = "' . $context['session_var'] . '";
		var current_header_smiley = ';
	if(empty($options['expand_header_smiley']))
		$context['html_headers'] .= 'false'; 
	else 
		$context['html_headers'] .= 'true';

	$context['html_headers'] .= '
		var current_header_bbc = ';
	if(empty($options['expand_header_bbc']))
		$context['html_headers'] .= 'false'; 
	else 
		$context['html_headers'] .= 'true';			
	$context['html_headers'] .= '
	// ]]></script>
	
	<script type="text/javascript" src="'. $settings['default_theme_url']. '/scripts/TPShout.js?10"></script>';
	
	if(!empty($context['TPortal']['shoutbox_refresh']))
		$context['html_headers'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		window.setInterval("TPupdateShouts(\'fetch\')", '. $context['TPortal']['shoutbox_refresh'] * 1000 . ');
	// ]]></script>';

if(file_exists($settings['theme_dir'].'/TPShout.css'))
	$context['html_headers'] .= '
	<link rel="stylesheet" type="text/css" href="'. $settings['theme_url']. '/TPShout.css?fin20" />';
else
	$context['html_headers'] .= '
	<link rel="stylesheet" type="text/css" href="'. $settings['default_theme_url']. '/TPShout.css?fin20" />';

if($context['TPortal']['shoutbox_usescroll'] > 0)
	$context['html_headers'] .= '
	<script type="text/javascript" src="tp-files/tp-plugins/javascript/jquery.marquee.js"></script>
	<script type="text/javascript">
		$j(document).ready(function(){
			$j("marquee").marquee("tp_marquee").mouseover(function () {
					$j(this).trigger("stop");
				}).mouseout(function () {
					$j(this).trigger("start");
				});					
			});
	</script>';

if(isset($_GET['shout']))
{
	if($_GET['shout'] == 'admin')
		tpshout_admin();
	elseif($_GET['shout'] == 'del')
	{
		deleteShout();
		tpshout_bigscreen(true);
	}
	elseif($_GET['shout'] == 'save')
	{
		postShout();
		tpshout_bigscreen(true);
	}
	elseif($_GET['shout'] == 'fetch')
	{
		tpshout_bigscreen(true, $context['TPortal']['shoutbox_limit']);
	}
	else
	{
		$number = substr($_GET['shout'], 4);
		if(!is_numeric($number))
			$number = 10;
		tpshout_bigscreen(false, $number);
	}
}

// Post the shout via ajax
function postShout()
{
	global $context, $smcFunc, $user_info, $scripturl, $sourcedir;
	
	isAllowedTo('tp_can_shout');
	
	if(!empty($_GET['tp_shout']) && !empty($_GET['tp-shout-name']))
	{
		// Check the session id.
		checkSession('get');
		require_once($sourcedir . '/Subs-Post.php');	
		$oldshout = $smcFunc['htmlspecialchars'](substr($_GET['tp_shout'], 0, 300));
		$shout = $oldshout;
		preparsecode($shout);

		// collect the color for shoutbox
		$request= $smcFunc['db_query']('', '
			SELECT grp.online_color as onlineColor
			FROM ({db_prefix}members as m, {db_prefix}membergroups as grp)
			WHERE m.id_group = grp.id_group
			AND id_member = {int:user} LIMIT 1',
			array('user' => $context['user']['id'])
		);
		if($smcFunc['db_num_rows']($request) > 0)
		{
			$row = $smcFunc['db_fetch_row']($request);
			$context['TPortal']['usercolor'] = $row[0];
			$smcFunc['db_free_result']($request);
		}


		// Build the name with color for user, otherwise strip guests name of html tags.
		$shout_name = ($user_info['id'] != 0) ? '<a href="'.$scripturl.'?action=profile;u='.$user_info['id'].'"' : strip_tags($_GET['tp-shout-name']);
		if(!empty($context['TPortal']['usercolor']))
			$shout_name .= ' style="color: '. $context['TPortal']['usercolor'] . '"';
		$shout_name .= ($user_info['id'] != 0) ? '>'.$context['user']['name'].'</a>' : '';

		$shout_time = time();

		// register the IP and userID, if any
		$ip = $user_info['ip'];
		$memID = $user_info['id'];

		if($shout != '')
			$smcFunc['db_insert']('INSERT',
				'{db_prefix}tp_shoutbox',
				array('value1' => 'string', 'value2' => 'string', 'value3' => 'string', 'type' => 'string','value4' => 'string', 'value5' => 'int'),
				array($shout, $shout_time, $shout_name, 'shoutbox', $ip, $memID),
				array('id')
			);
	}
}

// This is to delete a shout via ajax
function deleteShout()
{
	global $context, $smcFunc;

	// A copule of security checks
	checkSession('get');
	isAllowedTo('tp_can_admin_shout');

	if(!empty($_GET['s']))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}tp_shoutbox
			WHERE id = {int:id}',
			array('id' => (int) $_GET['s'])
		);
	}
}

function tpshout_admin()
{
	global $context, $scripturl, $txt, $smcFunc;
	
	// check permissions
	isAllowedTo('tp_can_admin_shout');

	if(isset($_GET['p']) && is_numeric($_GET['p']))
		$tpstart = $_GET['p'];
	else
		$tpstart = 0;

	loadtemplate('TPShout');

	$context['template_layers'][] = 'tpadm';
	$context['template_layers'][] = 'subtab';
	loadlanguage('TPortalAdmin');

	TPadminIndex('shout', true);
	$context['current_action'] = 'admin';

	if(isset($_REQUEST['send']) || isset($_REQUEST[$txt['tp-send']]) || isset($_REQUEST['tp_preview']) || isset($_REQUEST['TPadmin_blocks']))
	{
		$go = 0;
		foreach ($_POST as $what => $value) 
		{

			if(substr($what, 0, 18) == 'tp_shoutbox_remove')
			{
				$val = substr($what, 18);
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}tp_shoutbox 
					WHERE id = {int:shout}',
					array('shout' => $val)
				);
				$go = 2;
			}
			elseif($what == 'tp_shoutsdelall' && $value == 'ON')
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}tp_shoutbox 
					WHERE type = {string:type}',
					array('type' => 'shoutbox')
				);
				$go = 2;
			}
			elseif(substr($what, 0, 16) == 'tp_shoutbox_item')
			{
				$val = substr($what, 16);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}tp_shoutbox 
					SET value1 = {string:val1}
					WHERE id = {int:val}',
					array('val1' => $smcFunc['htmlspecialchars']($value, ENT_QUOTES), 'val' => $val)
				);
				$go = 2;
			}			
			else
			{
				$what = substr($what, 3);
				if($what == 'shoutbox_smile')
					$changeArray['show_shoutbox_smile'] = $value;
				if($what == 'shoutbox_icons')
					$changeArray['show_shoutbox_icons'] = $value;
				if($what == 'shoutbox_height')
					$changeArray['shoutbox_height'] = $value;
				if($what == 'shoutbox_usescroll')
					$changeArray['shoutbox_usescroll'] = $value;
				if($what == 'shoutbox_scrollduration')
				{
					if($value > 5)
						$value = 5;
					elseif($value < 1)
						$value = 1;
					$changeArray['shoutbox_scrollduration'] = $value;
				}
				if($what == 'shoutbox_limit') 
				{
					if(!is_numeric($value))
						$value = 10;
					$changeArray['shoutbox_limit'] = $value;
				}				
				if($what == 'shoutbox_refresh')
				{
					if(empty($value))
						$value = '0';
					$changeArray['shoutbox_refresh'] = $value;
				}
				if($what == 'show_profile_shouts')
					$changeArray['profile_shouts_hide'] = $value;
			}
		}
		updateTPSettings($changeArray);
		
		if(empty($go))
			redirectexit('action=tpmod;shout=admin;settings');
		else
			redirectexit('action=tpmod;shout=admin');
	}
	
	// get latest shouts for admin section
	// check that a member has been filtered
	if(isset($_GET['u']))
		$memID = $_GET['u'];
	// check that a IP has been filtered
	if(isset($_GET['ip']))
		$ip = $_GET['ip'];
	if(isset($_GET['s']))
		$single = $_GET['s'];

	$context['TPortal']['admin_shoutbox_items'] = array();

	if(isset($memID))
	{
		$request =  $smcFunc['db_query']('', '
			SELECT COUNT(*) FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type} 
			AND value5 = {int:val5} 
			AND value7 = {int:val7}',
			array('type' => 'shoutbox', 'val5' => $memID, 'val7' => 0)
		);
		$weh = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$allshouts = $weh[0];
		$context['TPortal']['admin_shoutbox_items_number'] = $allshouts;
		$context['TPortal']['shoutbox_pageindex'] = 'Member '.$memID.' filtered (<a href="'.$scripturl.'?action=tpmod;shout=admin">' . $txt['remove'] . '</a>) <br />'.TPageIndex($scripturl.'?action=tpmod;shout=admin;u='.$memID, $tpstart, $allshouts, 10, true);
		$request = $smcFunc['db_query']('', '
			SELECT * FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type} 
			AND value5 = {int:val5} 
			AND value7 = {int:val7} 
			ORDER BY value2 DESC LIMIT {int:start},10',
			array('type' => 'shoutbox', 'val5'=> $memID, 'val7' => 0, 'start' => $tpstart)
		);
	}
	elseif(isset($ip))
	{
		$request =  $smcFunc['db_query']('', '
			SELECT COUNT(*) FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type}
			AND value4 = {string:val4} 
			AND value7 = {int:val7}',
			array('type' => 'shoutbox', 'val4' => $ip, 'val7' => 0)
		);
		$weh = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$allshouts = $weh[0];
		$context['TPortal']['admin_shoutbox_items_number'] = $allshouts;
		$context['TPortal']['shoutbox_pageindex'] = 'IP '.$ip.' filtered (<a href="'.$scripturl.'?action=tpmod;shout=admin">' . $txt['remove'] . '</a>) <br />'.TPageIndex($scripturl.'?action=tpmod;shout=admin;ip='.urlencode($ip) , $tpstart, $allshouts, 10,true);
		$request =  $smcFunc['db_query']('', '
			SELECT * FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type}
			AND value4 = {string:val4} 
			AND value7 = {int:val7} 
			ORDER BY value2 DESC LIMIT {int:start}, 10',
			array('type' => 'shoutbox', 'val4' => $ip, 'val7' => 0, 'start' => $tpstart)
		);
	}
	elseif(isset($single))
	{
		// check session
		checkSession('get');
		$context['TPortal']['shoutbox_pageindex'] = '';
		$request = $smcFunc['db_query']('', '
			SELECT * FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type} 
			AND value7 = {int:val7} 
			AND id = {int:shout}',
			array('type' => 'shoutbox', 'val7' => 0, 'shout' => $single)
		);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type} 
			AND value7 = {int:val7}',
			array('type' => 'shoutbox', 'val7' => 0)
		);
		$weh = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$allshouts = $weh[0];
		$context['TPortal']['admin_shoutbox_items_number'] = $allshouts;
		$context['TPortal']['shoutbox_pageindex'] = TPageIndex($scripturl.'?action=tpmod;shout=admin', $tpstart, $allshouts, 10,true);
		$request = $smcFunc['db_query']('', '
			SELECT * FROM {db_prefix}tp_shoutbox 
			WHERE type = {string:type} 
			AND value7 = {int:val7} 
			ORDER BY value2 DESC LIMIT {int:start}, 10',
			array('type' => 'shoutbox', 'val7' => 0, 'start' => $tpstart)
		);
	}

	if($smcFunc['db_num_rows']($request) > 0)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$context['TPortal']['admin_shoutbox_items'][] = array(
				'id' => $row['id'],
				'body' => html_entity_decode($row['value1'], ENT_QUOTES),
				'poster' => $row['value3'],
				'timestamp' => $row['value2'],
				'time' => timeformat($row['value2']),
				'ip' => $row['value4'],
				'ID_MEMBER' => $row['value5'],
				'sort_member' => '<a href="'.$scripturl.'?action=tpmod;shout=admin;u='.$row['value5'].'">'.$txt['tp-allshoutsbymember'].'</a>', 
				'is_whisper' => $row['value6'],
				'whisper_who' => $row['value8'],
				'sort_ip' => '<a href="'.$scripturl.'?action=tpmod;shout=admin;ip='.$row['value4'].'">'.$txt['tp-allshoutsbyip'].'</a>',
				'single' => isset($single) ? '<hr><a href="'.$scripturl.'?action=tpmod;shout=admin"><b>'.$txt['tp-allshouts'].'</b></a>' : '',
			);
		}
		$smcFunc['db_free_result']($request);
	}


	// setup menu items
	if (allowedTo('tp_can_admin_shout'))
	{
		$context['TPortal']['subtabs'] = array(
			'shoutbox_settings' => array(
				'text' => 'tp-settings',
				'url' => $scripturl . '?action=tpmod;shout=admin;settings',
				'active' => (isset($_GET['action']) && ($_GET['action']=='tpmod' || $_GET['action']=='tpadmin' ) && isset($_GET['shout']) && $_GET['shout']=='admin' && isset($_GET['settings'])) ? true : false,
			),
			'shoutbox' => array(
				'text' => 'tp-tabs10',
				'url' => $scripturl . '?action=tpmod;shout=admin',
				'active' => (isset($_GET['action']) && ($_GET['action']=='tpmod' || $_GET['action']=='tpadmin' ) && isset($_GET['shout']) && $_GET['shout']=='admin' && !isset($_GET['settings'])) ? true : false,
			),
		);
		$context['admin_header']['tp_shout'] = $txt['tp_shout'];
	}
	// on settings screen?
	if(isset($_GET['settings']))
		$context['sub_template'] = 'tpshout_admin_settings';
	else
		$context['sub_template'] = 'tpshout_admin';
	
	$context['page_title'] = 'Shoutbox admin';

	tp_hidebars();

}
function tpshout_bigscreen($state = false, $number = 10)
{
	global $context;
	
	loadtemplate('TPShout');

	$context['TPortal']['rendershouts'] = tpshout_fetch($state, $number);
	TP_setThemeLayer('tpshout', 'TPShout', 'tpshout_bigscreen');
	$context['page_title'] = 'Shoutbox';
}
// fetch all the shouts for output
function tpshout_fetch($render = true, $limit = 1, $swap = false)
{
    global $context, $scripturl, $txt, $modSettings, $smcFunc;

	// get x number of shouts
	$context['TPortal']['profile_shouts_hide'] = empty($context['TPortal']['profile_shouts_hide']) ? '0' : '1';
	$context['TPortal']['usercolor']='';
	// collect the color for shoutbox
	$request = $smcFunc['db_query']('', '
		SELECT grp.online_color as onlineColor 
		FROM ({db_prefix}members as m, {db_prefix}membergroups as grp)
		WHERE m.id_group = grp.id_group
		AND id_member = {int:user} LIMIT 1',
		array('user' => $context['user']['id'])
	);
	if($smcFunc['db_num_rows']($request) > 0){
		$row = $smcFunc['db_fetch_row']($request);
		$context['TPortal']['usercolor'] = $row[0];
		$smcFunc['db_free_result']($request);
	}

	if(is_numeric($context['TPortal']['shoutbox_limit']) && $limit == 1)
		$limit = $context['TPortal']['shoutbox_limit'];

	// don't fetch more than a hundred - save the poor server! :D
	$nshouts = '';
	if($limit > 100)
		$limit = 100;
	
	if($render)
		loadtemplate('TPShout');

	$request =  $smcFunc['db_query']('', '
		SELECT s.*, IFNULL(s.value3, mem.real_name) as realName,
			mem.avatar,	IFNULL(a.id_attach, 0) AS ID_ATTACH, a.filename, a.attachment_type as attachmentType
		FROM {db_prefix}tp_shoutbox as s 
		LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = s.value5)
		LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = s.value5 and a.attachment_type!=3)
		WHERE s.value7 = {int:val7}
		ORDER BY s.value2 DESC LIMIT {int:limit}',
		array('val7' => 0, 'limit' => $limit)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$nshouts = '<div id="allshouts'.(!$render ? '_big' : '').'" class="qscroller'.(!$render ? '_big' : '').'"></div><div class="hide'.(!$render ? '_big' : '').'">';
		$ns = array();
		while($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['avatar'] = $row['avatar'] == '' ? ($row['ID_ATTACH'] > 0 ? '<img src="' . (empty($row['attachmentType']) ? $scripturl . '?action=dlattach;attach=' . $row['ID_ATTACH'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename']) . '" alt="&nbsp;"  />' : '') : (stristr($row['avatar'], 'http://') ? '<img src="' . $row['avatar'] . '" alt="&nbsp;" />' : '<img src="' . $modSettings['avatar_url'] . '/' . $smcFunc['htmlspecialchars']($row['avatar']) . '" alt="&nbsp;" />');
			$row['value1'] = parse_bbc(censorText($row['value1']), true);
			$ns[] = template_singleshout($row);
		}
		$nshouts .= implode('', $ns);
		
		$nshouts .= '</div>';

		$context['TPortal']['shoutbox'] = $nshouts;
		$smcFunc['db_free_result']($request);
	}
   // include the scolling code if shoutbox is set to scroll
	// its from a block, render it
	if($render)
		template_tpshout_shoutblock();
	else
		return $nshouts;

}

function shout_bcc_code($collapse = true) 
{
    global $context, $txt, $settings, $options;

	loadLanguage('Post');
	
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		function tp_bbc_highlight(something, mode)
		{
			something.style.backgroundImage = "url(" + smf_images_url + (mode ? "/bbc/bbc_hoverbg.gif)" : "/bbc/bbc_bg.gif)");
		}
	// ]]></script>';

	// The below array makes it dead easy to add images to this page. Add it to the array and everything else is done for you!
	$context['tp_bbc_tags'] = array();
	$context['tp_bbc_tags2'] = array();
	$context['tp_bbc_tags'][] = array(
		'bold' => array('code' => 'b', 'before' => '[b]', 'after' => '[/b]', 'description' => $txt['bold']),
		'italicize' => array('code' => 'i', 'before' => '[i]', 'after' => '[/i]', 'description' => $txt['italic']),
		'img' => array('code' => 'img', 'before' => '[img]', 'after' => '[/img]', 'description' => $txt['image']),
		'quote' => array('code' => 'quote', 'before' => '[quote]', 'after' => '[/quote]', 'description' => $txt['bbc_quote']),
	);
	$context['tp_bbc_tags2'][] = array(
		'underline' => array('code' => 'u', 'before' => '[u]', 'after' => '[/u]', 'description' => $txt[ 'underline']),
		'strike' => array('code' => 's', 'before' => '[s]', 'after' => '[/s]', 'description' => $txt['strike']),
		'glow' => array('code' => 'glow', 'before' => '[glow=red,2,300]', 'after' => '[/glow]', 'description' => $txt[ 'glow']),
		'shadow' => array('code' => 'shadow', 'before' => '[shadow=red,left]', 'after' => '[/shadow]', 'description' => $txt[ 'shadow']),
		'move' => array('code' => 'move', 'before' => '[move]', 'after' => '[/move]', 'description' => $txt[ 'marquee']),
	);
	
	if($collapse)
		echo '
	<a href="#" onclick="expandHeaderBBC(!current_header_bbc, ' . ($context['user']['is_guest'] ? 'true' : 'false') . ', \'' . $context['session_id'] . '\'); return false;">
		<img id="expand_bbc" src="', $settings['tp_images_url'], '/', empty($options['expand_header_bbc']) ? 'TPexpand.gif' : 'TPcollapse.gif', '" alt="*" title="', $txt['upshrink_description'], '" style="margin-right: 5px; position: relative; top: 5px;" align="left" />
	</a>
<div id="shoutbox_bbc">';
	else
		echo '<div>';

	$found_button = false;
	// Here loop through the array, printing the images/rows/separators!
	if(isset($context['tp_bbc_tags'][0]) && count($context['tp_bbc_tags'][0])>0)
	{
		foreach ($context['tp_bbc_tags'][0] as $image => $tag)
		{
			// Is there a "before" part for this bbc button? If not, it can't be a button!!
			if (isset($tag['before']))
			{
				// Is this tag disabled?
				if (!empty($context['disabled_tags'][$tag['code']]))
					continue;

				$found_button = true;

				// If there's no after, we're just replacing the entire selection in the post box.
				if (!isset($tag['after']))
					echo '<a href="javascript:void(0);" onclick="replaceText(\'', $tag['before'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;">';
				// On the other hand, if there is one we are surrounding the selection ;).
				else
					echo '<a href="javascript:void(0);" onclick="surroundText(\'', $tag['before'], '\', \'', $tag['after'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;">';

				// Okay... we have the link. Now for the image and the closing </a>!
				echo '<img onmouseover="tp_bbc_highlight(this, true);" onmouseout="if (window.tp_bbc_highlight) tp_bbc_highlight(this, false);" src="', $settings['images_url'], '/bbc/', $image, '.gif" align="bottom" width="23" height="22" alt="', $tag['description'], '" title="', $tag['description'], '" style="background-image: url(', $settings['images_url'], '/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;" /></a>';
			}
			// I guess it's a divider...
			elseif ($found_button)
			{
				echo '<img src="', $settings['images_url'], '/bbc/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';
				$found_button = false;
			}
		}
	}
	
	if($collapse)
		echo '
	<div id="expandHeaderBBC"', empty($options['expand_header_bbc']) ? ' style="display: none;"' : 'style="display: inline;"' , '>';
	else
		echo '
	<div style="display: inline;">';

	$found_button = false;
	// Here loop through the array, printing the images/rows/separators!
	if(isset($context['tp_bbc_tags2'][0]) && count($context['tp_bbc_tags2'][0])>0)
	{
		foreach ($context['tp_bbc_tags2'][0] as $image => $tag)
		{
			// Is there a "before" part for this bbc button? If not, it can't be a button!!
			if (isset($tag['before']))
			{
				// Is this tag disabled?
				if (!empty($context['disabled_tags'][$tag['code']]))
					continue;

				$found_button = true;

				// If there's no after, we're just replacing the entire selection in the post box.
				if (!isset($tag['after']))
					echo '<a href="javascript:void(0);" onclick="replaceText(\'', $tag['before'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;">';
				// On the other hand, if there is one we are surrounding the selection ;).
				else
					echo '<a href="javascript:void(0);" onclick="surroundText(\'', $tag['before'], '\', \'', $tag['after'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;">';

				// Okay... we have the link. Now for the image and the closing </a>!
				echo '<img onmouseover="tp_bbc_highlight(this, true);" onmouseout="if (window.tp_bbc_highlight) tp_bbc_highlight(this, false);" src="', $settings['images_url'], '/bbc/', $image, '.gif" align="bottom" width="23" height="22" alt="', $tag['description'], '" title="', $tag['description'], '" style="background-image: url(', $settings['images_url'], '/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;" /></a>';
			}
			// I guess it's a divider...
			elseif ($found_button)
			{
				echo '<img src="', $settings['images_url'], '/bbc/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';
				$found_button = false;
			}
		}
	}
	// Print a drop down list for all the colors we allow!
	if (!isset($context['shout_disabled_tags']['color']))
		echo ' <br /><select onchange="surroundText(\'[color=\' + this.options[this.selectedIndex].value.toLowerCase() + \']\', \'[/color]\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); this.selectedIndex = 0; document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '.focus(document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '.caretPos);" style="margin: 5px auto 10px auto;">
						<option value="" selected="selected">'. $txt['change_color']. '</option>
						<option value="Black">Black</option>
						<option value="Red">Red</option>
						<option value="Yellow">Yellow</option>
						<option value="Pink">Pink</option>
						<option value="Green">Green</option>
						<option value="Orange">Orange</option>
						<option value="Purple">Purple</option>
						<option value="Blue">Blue</option>
						<option value="Beige">Beige</option>
						<option value="Brown">Brown</option>
						<option value="Teal">Teal</option>
						<option value="Navy">Navy</option>
						<option value="Maroon">Maroon</option>
						<option value="LimeGreen">LimeGreen</option>
					</select>';
	echo '<br />';

	$found_button = false;
	// Print the bottom row of buttons!
	if(isset($context['tp_bbc_tags'][1]) && count($context['tp_bbc_tags'][1])>0)
	{
		foreach ($context['tp_bbc_tags'][1] as $image => $tag)
		{
			if (isset($tag['before']))
			{
				// Is this tag disabled?
				if (!empty($context['shout_disabled_tags'][$tag['code']]))
					continue;

				$found_button = true;

				// If there's no after, we're just replacing the entire selection in the post box.
				if (!isset($tag['after']))
					echo '<a href="javascript:void(0);" onclick="replaceText(\'', $tag['before'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;">';
				// On the other hand, if there is one we are surrounding the selection ;).
				else
					echo '<a href="javascript:void(0);" onclick="surroundText(\'', $tag['before'], '\', \'', $tag['after'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['shout_post_box_name'], '); return false;">';

				// Okay... we have the link. Now for the image and the closing </a>!
				echo '<img onmouseover="tp_bbc_highlight(this, true);" onmouseout="if (window.tp_bbc_highlight) tp_bbc_highlight(this, false);" src="', $settings['images_url'], '/bbc/', $image, '.gif" align="bottom" width="23" height="22" alt="', $tag['description'], '" title="', $tag['description'], '" style="background-image: url(', $settings['images_url'], '/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;" /></a>';
			}
			// I guess it's a divider...
			elseif ($found_button)
			{
				echo '<img src="', $settings['images_url'], '/bbc/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />';
				$found_button = false;
			}
		}
	}
	echo '</div>
	</div>';

}

function shout_smiley_code()
{
	global $context, $settings, $user_info, $txt, $modSettings, $db_prefix, $smcFunc;
  
	// Initialize smiley array...
	$context['tp_smileys'] = array(
		'postform' => array(),
		'popup' => array(),
	);
	// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
	if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
		$context['tp_smileys']['postform'][] = array(
			'smileys' => array(
				array('code' => ':)', 'filename' => 'smiley.gif', 'description' => $txt['icon_smiley']),
				array('code' => ';)', 'filename' => 'wink.gif', 'description' => $txt['icon_wink']),
				array('code' => ':D', 'filename' => 'cheesy.gif', 'description' => $txt['icon_cheesy']),
				array('code' => ';D', 'filename' => 'grin.gif', 'description' => $txt['icon_grin']),
				array('code' => '>:(', 'filename' => 'angry.gif', 'description' => $txt['icon_angry']),
				array('code' => ':(', 'filename' => 'sad.gif', 'description' => $txt[ 'icon_sad']),
				array('code' => ':o', 'filename' => 'shocked.gif', 'description' => $txt['icon_shocked']),
				array('code' => '8)', 'filename' => 'cool.gif', 'description' => $txt[ 'icon_cool']),
				array('code' => '???', 'filename' => 'huh.gif', 'description' => $txt['icon_huh']),
				array('code' => '::)', 'filename' => 'rolleyes.gif', 'description' => $txt[ 'icon_rolleyes']),
				array('code' => ':P', 'filename' => 'tongue.gif', 'description' => $txt['icon_tongue']),
				array('code' => ':-[', 'filename' => 'embarrassed.gif', 'description' => $txt['icon_embarrassed']),
				array('code' => ':-X', 'filename' => 'lipsrsealed.gif', 'description' => $txt['icon_lips']),
				array('code' => ':-\\', 'filename' => 'undecided.gif', 'description' => $txt[ 'icon_undecided']),
				array('code' => ':-*', 'filename' => 'kiss.gif', 'description' => $txt['icon_kiss']),
				array('code' => ':\'(', 'filename' => 'cry.gif', 'description' => $txt['icon_cry'])
			),
			'last' => true,
		);
	elseif ($user_info['smiley_set'] != 'none')
	{
		if (($temp = cache_get_data('posting_smileys', 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
			  SELECT code, filename, description, smiley_row, hidden
				FROM {db_prefix}smileys
				WHERE hidden IN ({int:val1}, {int:val2})
				ORDER BY smiley_row, smiley_order',
				array('val1' => 0,
				      'val2' => 2)
				);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$row['code'] = htmlspecialchars($row['code']);
				$row['filename'] = htmlspecialchars($row['filename']);
				$row['description'] = htmlspecialchars($row['description']);

				$context['tp_smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
			}
			$smcFunc['db_free_result']($request);

			cache_put_data('posting_smileys', $context['tp_smileys'], 480);
		}
		else
			$context['tp_smileys'] = $temp;
	}
	// Clean house... add slashes to the code for javascript.
	foreach (array_keys($context['tp_smileys']) as $location)
	{
		foreach ($context['tp_smileys'][$location] as $j => $row)
		{
			$n = count($context['tp_smileys'][$location][$j]['smileys']);
			for ($i = 0; $i < $n; $i++)
			{
				$context['tp_smileys'][$location][$j]['smileys'][$i]['code'] = addslashes($context['tp_smileys'][$location][$j]['smileys'][$i]['code']);
				$context['tp_smileys'][$location][$j]['smileys'][$i]['js_description'] = addslashes($context['tp_smileys'][$location][$j]['smileys'][$i]['description']);
			}

			$context['tp_smileys'][$location][$j]['smileys'][$n - 1]['last'] = true;
		}
		if (!empty($context['tp_smileys'][$location]))
			$context['tp_smileys'][$location][count($context['tp_smileys'][$location]) - 1]['last'] = true;
	}
	$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];
}

function print_shout_smileys($collapse = true) 
{
	global $context, $txt, $settings, $options;
  
	loadLanguage('Post');
  
	if($collapse)
		echo '
	<a href="#" onclick="expandHeaderSmiley(!current_header_smiley, '. ($context['user']['is_guest'] ? 'true' : 'false') .', \''. $context['session_id'] .'\'); return false;">
		<img id="expand_smiley" src="', $settings['tp_images_url'], '/', empty($options['expand_header_smiley']) ? 'TPexpand.gif' : 'TPcollapse.gif', '" alt="*" title="', $txt['upshrink_description'], '" style="margin-right: 5px; position: relative; top: 2px;" align="left" />
	</a>
	<div id="shoutbox_smiley">
		';
	else
		echo '
	<div>';

	// Now start printing all of the smileys.
	if (!empty($context['tp_smileys']['postform']))
	{
		// counter...
		$sm_counter=0;
		// Show each row of smileys ;).
		foreach ($context['tp_smileys']['postform'] as $smiley_row)
		{
			foreach ($smiley_row['smileys'] as $smiley)
			{
				if($sm_counter == 5 && $collapse)
					echo '
			<div id="expandHeaderSmiley"', empty($options['expand_header_smiley']) ? ' style="display: none;"' : 'style="display: inline;"' , '>';

				echo '
					<a href="javascript:void(0);" onclick="replaceText(\' ', $smiley['code'], '\', document.forms.', $context['tp_shoutbox_form'], '.', $context['tp_shout_post_box_name'], '); return false;"><img src="', $settings['smileys_url'], '/', $smiley['filename'], '" align="bottom" alt="', $smiley['description'], '" title="', $smiley['description'], '" /></a>';
				$sm_counter++;
			}
			// If this isn't the last row, show a break.
			if (empty($smiley_row['last']))
				echo '<br />';
		}
	}

	echo '
		</div>
	</div>';
}

// show a dedicated frontpage
function tpshout_frontpage()
{
    global  $context;

	loadtemplate('TPShout');

//	$context['sub_template'] = 'tpshout_frontpage';
	$context['page_title'] = 'Shoutbox frontpage';

	$context['sub_template'] = 'tpshout_bigscreen';
	$context['page_title'] = 'Shoutbox';
	$context['TPortal']['rendershouts'] = tpshout_fetch(false, '30');
	
}
function shoutbox_update()
{
	global $smcFunc;

	$settings_array = array(
	 'shoutbox_height' => '120' ,
	 'shoutbox_limit' => '5' ,
	 'guest_shout' => '0' ,
	 'shoutbox_usescroll' => '0',
	 'shoutbox_scrollduration' => '2000',
	 'shoutbox_scrolldelay' => '1000',
	 'shoutbox_scrolldirection' => 'vert',
	 'shoutbox_scrolleasing' => 'Cubic',
	 'show_shoutbox_archive' => '50',
	 'shoutbox_version' => '101',
	 'show_shoutbox_smile' => '1',
	 'show_shoutbox_icons' => '1',
		'profile_shouts_hide' => '0',
 	);

	foreach($settings_array as $what => $val){
		$sjekk = $smcFunc['db_query']('', '
			SELECT * FROM {db_prefix}tp_settings 
			WHERE name = {string:name}',
			array('name' => $what)
		);
		if($smcFunc['db_num_rows']($sjekk) < 1){
			$smcFunc['db_query']('INSERT',
				'{db_prefix}tp_settings',
				array('name' => 'string', 'value' => 'string'),
				array($what, $val),
				array('id')
			);
		}
		else
		{
			if($what == 'shoutbox_version')
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}tp_settings 
					SET value = {string:val} 
					WHERE name = {string:name}',
					array('val' => $val, 'name' => $what)
				);
				
			$smcFunc['db_free_result']($sjekk);
		}
	}
	// update profile section
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}tp_modules 
		SET profile = {string:prof}
		WHERE modulename LIKE {string:mod}',
		array('prof' => 'tpshout_profile', 'mod' => 'TPshout')
	);

	return;
}
?>