#!/usr/bin/php
<?PHP

require_once ( '/data/project/quest/scripts/Quest.php' ) ;

$quest = new Quest ;

$command = $argv[1]??'' ;

if ( $command == 'run_query' ) {
	$query_id = ($argv[2]??0)*1 ;
	if ( $query_id<=0 ) die ( "Bad query ID\n" ) ;
	$quest->run_query ( $query_id ) ;
} else if ( $command == 'run_late' ) {
	$quest->run_overdue_queries () ;
} else if ( $command == 'background' ) {
	while ( true ) {
		try {
			$quest->run_overdue_queries () ;
		} catch(Exception $e) {
			print $e->getMessage()."\n" ;
		}
		sleep ( 5 ) ;
	}
} else {
	die ( "Bad command {$command}\n" ) ;
}

?>