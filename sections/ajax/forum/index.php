<?
// Already done in /sections/ajax/index.php
//enforce_login();

if (!empty($LoggedUser['DisableForums'])) {
	print json_encode(array('status' => 'failure'));
	die();
}
else {
	include(SERVER_ROOT.'/sections/forums/functions.php');
	// Replace the old hard-coded forum categories
	unset($ForumCats);
	$ForumCats = $Cache->get_value('forums_categories');
	if ($ForumCats === false) {
		$DB->query("SELECT ID, Name FROM forums_categories");
		$ForumCats = array();
		while (list($ID, $Name) =  $DB->next_record()) {
			$ForumCats[$ID] = $Name;
		}
		$Cache->cache_value('forums_categories', $ForumCats, 0); //Inf cache.
	}

	//This variable contains all our lovely forum data
	if(!$Forums = $Cache->get_value('forums_list')) {
		$DB->query("SELECT
			f.ID,
			f.CategoryID,
			f.Name,
			f.Description,
			f.MinClassRead,
			f.MinClassWrite,
			f.MinClassCreate,
			f.NumTopics,
			f.NumPosts,
			f.LastPostID,
			f.LastPostAuthorID,
			f.LastPostTopicID,
			f.LastPostTime,
			COUNT(sr.ThreadID) AS SpecificRules,
			t.Title,
			t.IsLocked,
			t.IsSticky
			FROM forums AS f
			JOIN forums_categories AS fc ON fc.ID = f.CategoryID
			LEFT JOIN forums_topics as t ON t.ID = f.LastPostTopicID
			LEFT JOIN forums_specific_rules AS sr ON sr.ForumID = f.ID
			GROUP BY f.ID
			ORDER BY fc.Sort, fc.Name, f.CategoryID, f.Sort");
		$Forums = $DB->to_array('ID', MYSQLI_ASSOC, false);
		foreach($Forums as $ForumID => $Forum) {
			if(count($Forum['SpecificRules'])) {
				$DB->query("SELECT ThreadID FROM forums_specific_rules WHERE ForumID = ".$ForumID);
				$ThreadIDs = $DB->collect('ThreadID');
				$Forums[$ForumID]['SpecificRules'] = $ThreadIDs;
			}
		}
		unset($ForumID, $Forum);
		$Cache->cache_value('forums_list', $Forums, 0); //Inf cache.
	}

	if(empty($_GET['type']) || $_GET['type'] == 'main') {
		include(SERVER_ROOT.'/sections/ajax/forum/main.php');
	} else {
		switch ($_GET['type']) {
			case 'viewforum':
				include(SERVER_ROOT.'/sections/ajax/forum/forum.php');
				break;
			case 'viewthread':
				include(SERVER_ROOT.'/sections/ajax/forum/thread.php');
				break;
			default:
				print json_encode(array('status' => 'failure'));
				break;
		}
	}
}

// Function to get basic information on a forum
// Uses class CACHE
function get_forum_info($ForumID) {
	global $DB, $Cache;
	$Forum = $Cache->get_value('ForumInfo_'.$ForumID);
	if(!$Forum) {
		$DB->query("SELECT
			Name,
			MinClassRead,
			MinClassWrite,
			MinClassCreate,
			COUNT(forums_topics.ID) AS Topics
			FROM forums
			LEFT JOIN forums_topics ON forums_topics.ForumID=forums.ID
			WHERE forums.ID='$ForumID'
			GROUP BY ForumID");
		if($DB->record_count() == 0) {
			return false;
		}
		// Makes an array, with $Forum['Name'], etc.
		$Forum = $DB->next_record(MYSQLI_ASSOC);

		$Cache->cache_value('ForumInfo_'.$ForumID, $Forum, 86400); // Cache for a day
	}
	return $Forum;
}

?>
