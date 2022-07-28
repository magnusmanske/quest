<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR|E_ALL);
ini_set('display_errors', 'On');

require_once ( '/data/project/quest/scripts/Quest.php' ) ;
require_once ( '/data/project/wikidata-todo/public_html/php/Widar.php' ) ;

$oauth_url = 'https://www.mediawiki.org/w/index.php?title=Special:OAuth' ;
$widar = new Widar ( 'quest' , $oauth_url ) ;
$widar->attempt_verification_auto_forward ( 'https://quest.toolforge.org' ) ;
$widar->authorization_callback = 'https://quest.toolforge.org/api.php' ;
try {
	if ( $widar->render_reponse ( true ) ) exit ( 0 ) ;
} catch ( Exception $e ) {

}

function alter_command ( $command_id , $new_status ) {
	global $quest , $out , $widar ;
	$qs = $quest->get_qs() ;
	$qs->use_oauth = true ;
	$qs->oa = $widar->oa ;

	$username = $widar->get_username();
	$out['q'] = $quest->run_command ( $command_id ) ;
	$quest->set_command_status ( $command_id , $new_status , $username ) ;
	$out['new_status'] = $new_status ;
}

$quest = new Quest ;
$out = [ 'status' => 'OK' ] ;
$action = $quest->tfc->getRequest ( 'action' , '' ) ;

try {

	if ( $action == 'get_random_command' ) {
		$query_id = $quest->tfc->getRequest ( 'query_id' , 0 ) * 1 ;
		$out['commands'] = $quest->get_commands ( 5 , $query_id ) ;
	} else if ( $action == 'get_current_user_id' ) {
		try {
			$username = $widar->get_username();
			$out['user_id'] = $quest->get_or_create_user_id ( $username ) ;
		} catch(Exception $e) {
			$out['user_id'] = 0 ;
		}
	} else if ( $action == 'run_command' ) {
		$command_id = $quest->tfc->getRequest ( 'command_id' , 0 ) * 1 ;
		alter_command ( $command_id , 'DONE' ) ;
	} else if ( $action == 'bad_command' ) {
		$command_id = $quest->tfc->getRequest ( 'command_id' , 0 ) * 1 ;
		alter_command ( $command_id , 'BAD' ) ;
	} else if ( $action == 'get_queries' ) {
		$start = $quest->tfc->getRequest ( 'start' , 0 ) * 1 ;
		$batch_size = $quest->tfc->getRequest ( 'batch_size' , 50 ) * 1 ;
		$user_name = trim ( $quest->tfc->getRequest ( 'user_name' , '' ) ) ;
		$user_id = $user_name=='' ? 0 : $quest->get_or_create_user_id($user_name) ;
		$out['queries'] = $quest->get_query_batch ( $start , $batch_size , $user_id ) ;
	} else if ( $action == 'save_query' ) {
		$query = json_decode ( $quest->tfc->getRequest ( 'query' , (object)[] ) )  ;
		unset($query->user_name) ;
		$username = $widar->get_username();
		$user_id = $quest->get_or_create_user_id ( $username ) ;
		if ( $query->user_id != $user_id ) throw new Exception("You are not the owner of this query!") ;
		$query_id = $quest->save_query($query) ;
		$out['query'] = $quest->get_query_by_id($query_id) ;
	} else if ( $action == 'delete_query' ) {
		$query_id = $quest->tfc->getRequest ( 'query_id' , 0 ) * 1 ;
		$username = $widar->get_username();
		$user_id = $quest->get_or_create_user_id ( $username ) ;
		$query = $quest->get_query_by_id ( $query_id ) ;
		if ( $query->user_id != $user_id ) throw new Exception("You are not the owner of this query!") ;
		$quest->delete_query($query_id) ;

	} else {
		$out['status'] = "No/bad action '{$action}'" ;
	}

} catch(Exception $e) {
	$out['status'] = $e->getMessage() ;
}


header('Content-Type: application/json');
print json_encode ( $out ) ;

?>