<?php
/**********************************************************************************
* Subs-ManageSubForums.php - PHP implementation of the Split Forum Mod
***********************************************************************************
* This mod is licensed under the 2-clause BSD License, which can be found here:
*	http://opensource.org/licenses/BSD-2-Clause
***********************************************************************************
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
**********************************************************************************/
if (!defined('SMF')) 
	die('Hacking attempt...');

function get_installed_themes()
{
	global $smcFunc, $txt, $modSettings;

	isAllowedTo('admin_forum');
	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}themes
		WHERE variable = {string:name}
			AND id_member = 0
			AND id_theme IN ({raw:known})',
		array(
			'name' => 'name',
			'known' => $modSettings['knownThemes']
		)
	);
	$themes = array($txt['mboards_theme_default']);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[$row['id_theme']] = $row['value'];
	$smcFunc['db_free_result']($request);
	return $themes;
}

function get_categories()
{
	global $smcFunc, $txt;

	isAllowedTo('admin_forum');
	$cats = array(
	);
	$request = $smcFunc['db_query']('', '
		SELECT id_cat, name
		FROM {db_prefix}categories
		ORDER BY cat_order',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$cats[$row['id_cat']] = $row['name'];
	$smcFunc['db_free_result']($request);
	return $cats;
}

function get_membergroups()
{
	global $smcFunc, $txt;

	isAllowedTo('admin_forum');
	$primary = array(
		0 => $txt['no_primary_membergroup']
	);
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group > {int:moderator_group} 
			AND min_posts = -1
		ORDER BY id_group',
		array(
			'moderator_group' => 3,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$primary[$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);
	return $primary;
}

function get_subforum_max()
{
	global $subforum_tree;

	isAllowedTo('admin_forum');
	$max = 0;
	foreach ($subforum_tree as $subforum)
		$max = ($subforum['forumid'] > $max ? $subforum['forumid'] : $max);
	return $max;
}

function add_subforum(&$row)
{
	global $subforum_tree, $forumid, $sourcedir, $txt;

	isAllowedTo('admin_forum');

	// Define the contents of the array element:
	$new_forumid = (isset($row['forumid']) ? (int) $row['forumid'] : $forumid);
	$tree = array();
	foreach ($subforum_tree as $subforum)
		$tree[$subforum['forumid']] = $subforum;
	$tree[$new_forumid] = array(
		'forumid' => $new_forumid,
		'cookiename' => (isset($row['cookiename']) ? $row['cookiename'] : ''),
		'boardurl' => (isset($row['boardurl']) ? $row['boardurl'] : ''),
		'boardname' => (isset($row['boardname']) ? $row['boardname'] : ''),
		'subtheme' => (int) (isset($row['subtheme']) ? $row['subtheme'] : 0),
		'language' => (isset($row['language']) ? $row['language'] : ''),
		'forumdir' => (isset($row['forumdir']) ? $row['forumdir'] : ''),
		'favicon' => (isset($row['favicon']) ? $row['favicon'] : ''),
		'primary_membergroup' => (isset($row['primary_membergroup']) ? (int) $row['primary_membergroup'] : 0),
		'sp_portal' => (isset($row['sp_portal']) ? (int) $row['sp_portal'] : 0),
		'sp_standalone' => (isset($row['sp_standalone']) ? $row['sp_standalone'] : ''),
		'ez_portal_enable' => (isset($row['ez_portal_enable']) ? (int) $row['ez_portal_enable'] : 0),
		'ez_homepage_title' => (isset($row['ez_homepage_title']) ? $row['ez_homepage_title'] : ''),
		'ez_shoutbox' => (isset($row['ez_shoutbox']) ? (int) $row['ez_shoutbox'] : 0),
		'enable_pretty' => (isset($row['enable_pretty']) ? $row['enable_pretty'] : ''),
	);
	asort($tree);
	$subforum_tree = $tree;

	// Set the variable "subforum_tree" in the forum's Settings.php:
	require_once($sourcedir.'/Subs-Admin.php');
	updateSettingsFile(array('subforum_tree' => str_replace("\n", "", var_export($subforum_tree, true))));
}

function delete_subforum($sub, $write_settings = true)
{
	global $subforum_tree, $sourcedir;

	isAllowedTo('admin_forum');
	if ($sub == 0) return;
	unset($subforum_tree[(int) $sub]);
	if ($write_settings)
	{
		require_once($sourcedir.'/Subs-Admin.php');
		updateSettingsFile(array('subforum_tree' => str_replace("\n", "", var_export($subforum_tree, true))));
	}
}

function move_attached_categories($from, $dest)
{
	global $smcFunc;

	isAllowedTo('admin_forum');
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}categories
		SET forumid = {int:newid}
		WHERE forumid = {int:forumid}',
		array(
			'newid' => (int) $dest,
			'forumid' => (int) $from,
		)
	);
}

function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
{
	$arFrom = explode($ps, rtrim($from, $ps));
	$arTo = explode($ps, rtrim($to, $ps));
	while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0]))
	{
		array_shift($arFrom);
		array_shift($arTo);
	}
	$base = str_pad("", count($arFrom) * 3, '..' . $ps) . implode($ps, $arTo);
	return str_replace(DIRECTORY_SEPARATOR, '/', $base);
}

function remove_bad_aliases()
{
	global $smcFunc;
	return;

	// Gather some basic board information together:
	$result = $smcFunc['db_query']('', '
		SELECT
			IFNULL(b.id_board, 0) AS id_board, b.id_cat, c.forumid, b.alias_cat, b.alias_child
		FROM {db_prefix}categories AS c
			LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)'
	);
	$b = $c = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!empty($row['alias_cat']))
			$row['alias_cat'] = explode(',', $row['alias_cat']);
		else
			$row['alias_cat'] = array();

		if (!empty($row['alias_child']))
			$row['alias_child'] = explode(',', $row['alias_child']);
		else
			$row['alias_child'] = array();

		$b[$row['forumid']][$row['id_board']] = $row;
		$c[$row['forumid']][$row['id_cat']] = true;
	}
	$smcFunc['db_free_result']($result);

	// Process the entire array, looking for categories/boards that don't exist:
	foreach ($b as $forumid => $boards)
	{
		foreach ($boards as $id_board => $board)
		{
			// Remove aliased categories/boards that don't exist in that subforum:
			$update = false;
			foreach ($board['alias_cat'] as $id => $alias_cat)
			{
				if (empty($c[$forumid][$alias_cat]))
				{
					unset($b[$forumid][$id_board]['alias_cat'][$id]);
					$update = true;
				}
			}
			foreach ($board['alias_child'] as $id => $alias_child)
			{
				if (empty($b[$forumid][$alias_child]))
				{
					unset($b[$forumid][$id_board]['alias_child'][$id]);
					$update = true;
				}
			}

			// Do we need to update the database?  If not, continue on processing....
			if (!$update)
				continue;

			// Evidentally, we need to update the database with the new info:
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET alias_cat = {string:alias_cat}, alias_child = {string:alias_child}
				WHERE id_board = {int:id_board}',
				array(
					'id_board' => (int) $board,
					'alias_cat' => implode(',', $board['alias_cat']),
					'alias_child' => implode(',', $board['alias_child']),
				)
			);
		}
	}
}

/**********************************************************************************
* Functions supporting Simple Portal:
**********************************************************************************/
function use_default_sp_blocks($forum = -1)
{
	global $smcFunc;

	isAllowedTo('admin_forum');
	$forum = (int) $forum;

	$welcome_text = '<h2 style="text-align: center;">Welcome to SimplePortal!</h2>
<p>SimplePortal is one of several portal mods for Simple Machines Forum (SMF). Although always developing, SimplePortal is produced with the user in mind first. User feedback is the number one method of growth for SimplePortal, and our users are always finding ways for SimplePortal to grow. SimplePortal stays competative with other portal software by adding numerous user-requested features such as articles, block types and the ability to completely customize the portal page.</p>
<p>All this and SimplePortal has remained Simple! SimplePortal is built for simplicity and ease of use; ensuring the average forum administrator can install SimplePortal, configure a few settings, and show off the brand new portal to the users in minutes. Confusing menus, undesired pre-loaded blocks and settings that cannot be found are all avoided as much as possible. Because when it comes down to it, SimplePortal is YOUR portal, and should reflect your taste as much as possible.</p>
<p><strong>Ultimate Simplicity</strong>
<br />
The simplest portal you can ever think of... You only need a few clicks to install it through Package Manager. A few more to create your own blocks and articles. Your portal is ready to go within a couple of minutes, and simple to customise to reflect YOU.</p>
<p><strong>Install Friendly</strong>
<br />
With the ingenius design of install and update packages, SimplePortal is incredibly install and update friendly. You will never need any manual changes even on a heavily modified forum.</p>
<p><strong>Incredible Theme Support</strong>
<br />
The simple but powerful structure of SimplePortal brings you wide-range theme support too. You can use SimplePortal with all SMF themes by just adding a button for it.</p>
<p><strong>Professional Support</strong>
<br />
SimplePortal offers high quality professional support with its own well known support team.</p>';

	$default_blocks = array(
		'user_info' => array(
			'label' => 'User Info',
			'type' => 'sp_userInfo',
			'col' => 1,
			'row' => 1,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'whos_online' => array(
			'label' => 'Who&#039;s Online',
			'type' => 'sp_whosOnline',
			'col' => 1,
			'row' => 2,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'board_stats' => array(
			'label' => 'Board Stats',
			'type' => 'sp_boardStats',
			'col' => 1,
			'row' => 3,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'theme_select' => array(
			'label' => 'Theme Select',
			'type' => 'sp_theme_select',
			'col' => 1,
			'row' => 4,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'search' => array(
			'label' => 'Search',
			'type' => 'sp_quickSearch',
			'col' => 1,
			'row' => 5,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'news' => array(
			'label' => 'News',
			'type' => 'sp_news',
			'col' => 2,
			'row' => 1,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => 'title_default_class~|title_custom_class~|title_custom_style~|body_default_class~windowbg|body_custom_class~|body_custom_style~|no_title~1|no_body~',
			'forum' => $forum,
		),
		'welcome' => array(
			'label' => 'Welcome',
			'type' => 'sp_html',
			'col' => 2,
			'row' => 2,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => 'title_default_class~|title_custom_class~|title_custom_style~|body_default_class~windowbg|body_custom_class~|body_custom_style~|no_title~1|no_body~',
			'forum' => $forum,
		),
		'board_news' => array(
			'label' => 'Board News',
			'type' => 'sp_boardNews',
			'col' => 2,
			'row' => 3,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'recent_topics' => array(
			'label' => 'Recent Topics',
			'type' => 'sp_recent',
			'col' => 3,
			'row' => 1,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'top_poster' => array(
			'label' => 'Top Poster',
			'type' => 'sp_topPoster',
			'col' => 4,
			'row' => 1,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'recent_posts' => array(
			'label' => 'Recent Posts',
			'type' => 'sp_recent',
			'col' => 4,
			'row' => 2,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'staff' => array(
			'label' => 'Forum Staff',
			'type' => 'sp_staff',
			'col' => 4,
			'row' => 3,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'calendar' => array(
			'label' => 'Calendar',
			'type' => 'sp_calendar',
			'col' => 4,
			'row' => 4,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
		'top_boards' => array(
			'label' => 'Top Boards',
			'type' => 'sp_topBoards',
			'col' => 4,
			'row' => 5,
			'permission_set' => '3',
			'display' => '',
			'display_custom' => '',
			'style' => '',
			'forum' => $forum,
		),
	);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}sp_blocks',
		array(
			'label' => 'text',
			'type' => 'text',
			'col' => 'int',
			'row' => 'int',
			'permission_set' => 'int',
			'display' => 'text',
			'display_custom' => 'text',
			'style' => 'text',
			'forum' => 'text',
		),
		$default_blocks,
		array('id_block')
	);

	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_block) AS id, type
		FROM {db_prefix}sp_blocks
		WHERE type IN ({array_string:types}) AND forum = {int:forum}
		GROUP BY type
		LIMIT 4',
		array(
			'types' => array('sp_html', 'sp_boardNews', 'sp_calendar', 'sp_recent'),
			'forum' => $forum,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$block_ids[$row['type']] = $row['id'];
	$smcFunc['db_free_result']($request);

	$default_parameters = array(
		array(
			'id_block' => $block_ids['sp_html'],
			'variable' => 'content',
			'value' => htmlspecialchars($welcome_text),
		),
		array(
			'id_block' => $block_ids['sp_boardNews'],
			'variable' => 'avatar',
			'value' => 1,
		),
		array(
			'id_block' => $block_ids['sp_boardNews'],
			'variable' => 'per_page',
			'value' => 3,
		),
		array(
			'id_block' => $block_ids['sp_calendar'],
			'variable' => 'events',
			'value' => 1,
		),
		array(
			'id_block' => $block_ids['sp_calendar'],
			'variable' => 'birthdays',
			'value' => 1,
		),
		array(
			'id_block' => $block_ids['sp_calendar'],
			'variable' => 'holidays',
			'value' => 1,
		),
		array(
			'id_block' => $block_ids['sp_recent'],
			'variable' => 'type',
			'value' => 1,
		),
		array(
			'id_block' => $block_ids['sp_recent'],
			'variable' => 'display',
			'value' => 1,
		),
	);

	$smcFunc['db_insert']('replace',
		'{db_prefix}sp_parameters',
		array(
			'id_block' => 'int',
			'variable' => 'text',
			'value' => 'text',
		),
		$default_parameters,
		array()
	);
}

function delete_sp_blocks($forum)
{
	global $smcFunc;

	isAllowedTo('admin_forum');
	$forum = (int) $forum;

	// Get the id number for blocks with parameters:
	$request = $smcFunc['db_query']('', '
		SELECT b.id_block
		FROM {db_prefix}sp_blocks AS b, {db_prefix}sp_parameters AS p
		WHERE b.forum = {int:forum}',
		array(
			'forum' => $forum,
		)
	);
	$parameters = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$parameters[] = $row['id_block'];
	$smcFunc['db_free_result']($request);

	// Delete all blocks for the specified forum:
	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sp_blocks
		WHERE forum = {int:forum}',
		array(
			'forum' => $forum,
		)
	);

	// Delete the parameters for blocks from the specified forum:
	if (!empty($parameters))
	{
		$parameters = array_unique($parameters);
		$request = $smcFunc['db_query']('', '
			DELETE FROM {db_prefix}sp_parameters
			WHERE id_block IN ({array_int:id_blocks})',
			array(
				'id_blocks' => $parameters
			)
		);
	}
}

function copy_sp_blocks($from, $forum)
{
	global $smcFunc;

	isAllowedTo('admin_forum');
	$from = (int) $from;
	$forum = (int) $forum;

	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_block) AS id_block
		FROM {db_prefix}sp_blocks'
	);
	list($max) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Retrieve & modify all blocks for specified forum:
	$request = $smcFunc['db_query']('', '
		SELECT
			b.id_block, b.label, b.type, b.col, b.row, b.permission_set, b.groups_allowed, b.groups_denied,
			b.state, b.force_view, b.display, b.display_custom, b.style, b.forum, b.style, p.variable, p.value
		FROM {db_prefix}sp_blocks AS b
			LEFT JOIN {db_prefix}sp_parameters AS p ON (p.id_block = b.id_block)
		WHERE b.forum = {int:from}
		ORDER BY b.id_block ASC',
		array(
			'from' => $from,
		)
	);
	$blocks	= array();
	$parameters = array();
	$last = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$current = $row['id_block'];
		if ($current <> $last)
		{
			$max++;
			$row['id_block'] = $max;
			$last = $current;
		}
		if (!empty($row['variable']))
		{
			$parameters[] = array(
				'id_block' => $max,
				'variable' => $row['variable'],
				'value' => $row['value']
			);
		}
		$row['forum'] = $forum;
		unset($row['variable']);
		unset($row['value']);
		$blocks[$max] = $row;
	}
	$smcFunc['db_free_result']($request);

	// Place the new blocks into the Simple Portal table:
	$smcFunc['db_insert']('replace',
		'{db_prefix}sp_blocks',
		array(
			'id_block' => 'int',
			'label' => 'text',
			'type' => 'text',
			'col' => 'int',
			'row' => 'int',
			'permission_set' => 'int',
			'groups_allowed' => 'text',
			'groups_denied' => 'text',
			'state' => 'int',
			'force_view' => 'int',
			'display' => 'text',
			'display_custom' => 'text',
			'style' => 'text',
			'forum' => 'text',
		),
		$blocks,
		array('id_block')
	);

	// Place the parameters into the Simple Portal table:
	$smcFunc['db_insert']('replace',
		'{db_prefix}sp_parameters',
		array(
			'id_block' => 'int',
			'variable' => 'text',
			'value' => 'text',
		),
		$parameters,
		array()
	);
}

/**********************************************************************************
* Functions supporting EzPortal:
**********************************************************************************/
function ez_blocks_max()
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_layout) AS id_layout
		FROM {db_prefix}ezp_block_layout'
	);
	list($max) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	return (!empty($max['id_layout']) ? $max['id_layout'] : 0);
}

function use_default_ez_blocks($forum)
{
	global $smcFunc;
	$max = ez_blocks_max();

	// Place the blocks into the EzPortal table:
	$default_blocks = array(
		'user' => array(
			'id_layout' => $max + 1,
			'id_column' => 1,
			'id_block' => 4,
			'id_order' => 1,
			'customtitle' => 'User',
			'permissions' => '-1,0,1,2',
			'can_collapse' => 1,
			'active' => 1,
			'visibileactions' => '',
			'visibileboards' => '',
			'visibileareascustom' => '',
			'blockmanagers' => '',
			'blockdata' => 'EzBlockLoginBoxBlock',
			'id_icon' => 0,
			'visibilepages' => '',
			'hidetitlebar' => 0,
			'forum '=> $forum,
		),
		'stats' => array(
			'id_layout' => $max + 2,
			'id_column' => 1,
			'id_block' => 27,
			'id_order' => 2,
			'customtitle' => 'Stats ezBlock',
			'permissions' => '-1,0,1,2',
			'can_collapse' => 1,
			'active' => 1,
			'visibileactions' => '',
			'visibileboards' => '',
			'visibileareascustom' => '',
			'blockmanagers' => '',
			'blockdata' => 'EzBlockStatsBox',
			'id_icon' => 0,
			'visibilepages' => '',
			'hidetitlebar' => 0,
			'forum '=> $forum,
		),
		'recent_topics' => array(
			'id_layout' => $max + 3,
			'id_column' => 3,
			'id_block' => 7,
			'id_order' => 1,
			'customtitle' => 'Recent Topics',
			'permissions' => '-1,0,1,2',
			'can_collapse' => 1,
			'active' => 1,
			'visibileactions' => '',
			'visibileboards' => '',
			'visibileareascustom' => '',
			'blockmanagers' => '',
			'blockdata' => 'EzBlockRecentTopicsBlock',
			'id_icon' => 0,
			'visibilepages' => '',
			'hidetitlebar' => 0,
			'forum '=> $forum,
		),
		'parsebbc' => array(
			'id_layout' => $max + 4,
			'id_column' => 2,
			'id_block' => 26,
			'id_order' => 1,
			'customtitle' => 'ParseBBC ezBlock',
			'permissions' => '-1,0,1,2',
			'can_collapse' => 1,
			'active' => 1,
			'visibileactions' => '',
			'visibileboards' => '',
			'visibileareascustom' => 'portal',
			'blockmanagers' => '',
			'blockdata' => 'EzBlockParseBBCBlock',
			'id_icon' => 0,
			'visibilepages' => '',
			'hidetitlebar' => 0,
			'forum '=> $forum,
		),
	);
	$smcFunc['db_insert']('ignore',
		'{db_prefix}ezp_block_layout',
		array(
			'id_layout' => 'int',
			'id_column' => 'int',
			'id_block' => 'int',
			'id_order' => 'int',
			'customtitle' => 'text',
			'permissions' => 'text',
			'can_collapse' => 'int',
			'active' => 'int',
			'visibileactions' => 'text',
			'visibileboards' => 'text',
			'visibileareascustom' => 'text',
			'blockmanagers' => 'text',
			'blockdata' => 'text',
			'id_icon' => 'int',
			'visibilepages' => 'text',
			'hidetitlebar' => 'int',
			'forum' => 'text',
		),
		$default_blocks,
		array('id_layout')
	);
	
	// Place the parameters into the EzPortal table:
	$parameters = array(
		array(
			'id_parameter' => 8,
			'id_layout' => $max + 3,
			'data' => '10',
		),
		array(
			'id_parameter' => 9,
			'id_layout' => $max + 3,
			'data' => 'vertical',
		),
		array(
			'id_parameter' => 10,
			'id_layout' => $max + 3,
			'data' => 'true',
		),
		array(
			'id_parameter' => 46,
			'id_layout' => $max + 4,
			'data' => 'Welcome to your new install of ezPortal!

You can control which blocks are shown in the ezBlockManager.

Forum refers to your main board index.
Portal is the main homepage of your site. This block for instance is only shown on the portal page.


If you have any questions or need help post in our support forums at [url]http://www.ezportal.com[/url]',
		),
	);
	$smcFunc['db_insert']('replace',
		'{db_prefix}ezp_block_parameters_values',
		array(
			'id_parameter' => 'int',
			'id_layout' => 'int',
			'data' => 'text',
		),
		$parameters,
		array()
	);
}

function delete_ez_blocks($forum)
{
	global $smcFunc;

	// Get the id number for blocks with parameters:
	isAllowedTo('admin_forum');
	$request = $smcFunc['db_query']('', '
		SELECT id_layout
		FROM {db_prefix}ezp_block_layout
		WHERE forum = {int:forum}',
		array(
			'forum' => (int) $forum,
		)
	);
	$blocks = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$blocks[] = $row['id_layout'];
	$smcFunc['db_free_result']($request);

	// Delete all blocks for the specified forum:
	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ezp_block_layout
		WHERE id_layout IN ({array_int:id_blocks})',
		array(
			'id_blocks' => $blocks
		)
	);

	// Delete the parameters for blocks from the specified forum:
	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ezp_block_parameters_values
		WHERE id_layout IN ({array_int:id_blocks})',
		array(
			'id_blocks' => $blocks
		)
	);
}

function copy_ez_blocks($from, $forum)
{
	global $smcFunc;

	// Get the id number for blocks with parameters:
	isAllowedTo('admin_forum');
	$request = $smcFunc['db_query']('', '
		SELECT 
			id_layout, id_column, id_block, id_order, customtitle, active, permissions, can_collapse, visibileactions,
			visibileboards, visibileareascustom, blockmanagers, blockdata, id_icon, visibilepages, hidetitlebar
		FROM {db_prefix}ezp_block_layout
		WHERE forum = {int:forum}',
		array(
			'forum' => (int) $from,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$id_layout = $row['id_layout'];
		unset($row['id_layout']);
		$row['forum'] = (int) $forum;
		
		// Insert new block into the table:
		$smcFunc['db_insert']('ignore',
			'{db_prefix}ezp_block_layout',
			array(
				'id_column' => 'int',
				'id_block' => 'int',
				'id_order' => 'int',
				'customtitle' => 'text',
				'permissions' => 'text',
				'can_collapse' => 'text',
				'active' => 'int',
				'visibileactions' => 'text',
				'visibileboards' => 'text',
				'visibileareascustom' => 'text',
				'blockmanagers' => 'text',
				'blockdata' => 'text',
				'id_icon' => 'int',
				'visibilepages' => 'text',
				'hidetitlebar' => 'int',
				'forum' => 'text',
			),
			$row,
			array('id_layout')
		);
		$new_layout = $smcFunc['db_insert_id']('{db_prefix}messages', 'id_msg');
		
		$request2 = $smcFunc['db_query']('', '
			SELECT id_parameter, data
			FROM {db_prefix}ezp_block_parameters_values
			WHERE id_layout = {int:block}',
			array(
				'block' => $id_layout,
			)
		);		
		while ($row2 = $smcFunc['db_fetch_assoc']($request2))
		{
			$row2['id_layout'] = $new_layout;
			$smcFunc['db_insert']('replace',
				'{db_prefix}ezp_block_parameters_values',
				array(
					'id_parameter' => 'int',
					'data' => 'text',
					'id_layout' => 'int',
				),
				$row2,
				array()
			);
		}
		$smcFunc['db_free_result']($request2);
	}
	$smcFunc['db_free_result']($request);
}

?>