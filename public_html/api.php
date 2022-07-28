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
		$out['commands'] = $quest->get_commands ( 5 ) ;
	} else if ( $action == 'run_command' ) {
		$command_id = $quest->tfc->getRequest ( 'command_id' , 0 ) * 1 ;
		alter_command ( $command_id , 'DONE' ) ;
	} else if ( $action == 'bad_command' ) {
		$command_id = $quest->tfc->getRequest ( 'command_id' , 0 ) * 1 ;
		alter_command ( $command_id , 'BAD' ) ;
	 } else {
		$out['status'] = "No/bad action '{$action}'" ;
	}

} catch(Exception $e) {
	$out['status'] = $e->getMessage() ;
}


header('Content-Type: application/json');
print json_encode ( $out ) ;

?>