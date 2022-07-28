#!/usr/bin/php
<?PHP

require_once ( '/data/project/quest/scripts/Quest.php' ) ;

$quest = new Quest ;
$user_id = $quest->get_or_create_user_id ( 'Daniel_Mietchen' ) ;

#$quest->run_query ( 1 ) ;
$commands = $quest->get_commands () ;
print_r($commands);

?>