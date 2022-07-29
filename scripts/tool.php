#!/usr/bin/php
<?PHP

require_once ( '/data/project/quest/scripts/Quest.php' ) ;

function run_overdue_queries () {
	global $quest ;
	$ts = $quest->tfc->getCurrentTimestamp() ;
	$sql = "SELECT * FROM `queries` WHERE `next_run`<='{$ts}' AND `status`='OK' ORDER BY `next_run`" ;
	$result = $this->getSQL ( $sql ) ;
	while ($o = $result->fetch_object()) {
		try {
			$quest->run_query ( $o->id ) ;
		} catch(Exception $e) {
			$quest->update_query_status($o->id,'FAILED');
		}
	}
}

$quest = new Quest ;

$command = $argv[1]??'' ;

if ( $command == 'run_query' ) {
	$query_id = ($argv[2]??0)*1 ;
	if ( $query_id<=0 ) die ( "Bad query ID\n" ) ;
	$quest->run_query ( $query_id ) ;
} else if ( $command == 'run_late' ) {
	run_overdue_queries () ;
} else if ( $command == 'background' ) {
	while ( true ) {
		run_overdue_queries () ;
		sleep ( 5 ) ;
	}
} else {
	die ( "Bad command {$command}\n" ) ;
}

?>