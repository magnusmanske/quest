<?PHP

require_once ( '/data/project/wikidata-todo/public_html/php/ToolforgeCommon.php' ) ;
require_once ( '/data/project/quickstatements/public_html/quickstatements.php' ) ;
//require_once ( '/data/project/wikidata-todo/public_html/php/wikidata.php' ) ;

/*
NOTE:
By default, this uses [[User:Reinheitsgbot]] for edits.
To override:

$qs = $quest->get_qs() ;
$qs->use_oauth = true ;
$qs->oa = $OTHER_OA_TO_USE ;

*/

class Quest {
	public $tfc ;
	public $dbt ;
	private $conf_file = '/data/project/quest/reinheitsgebot.conf' ;

	function __construct () {
		$this->tfc = new ToolforgeCommon ;
		$this->dbt = $this->tfc->openDBtool ( 'quest_p' ) ;
	}

	public function get_or_create_user_id ( string $username ) : int {
		if ( trim($username) == '' ) return 0 ;
		$username = $this->escape ( $this->get_normalized_username($username) ) ;
		$sql = "SELECT `id` FROM `users` WHERE `name`='{$username}'" ;
		$result = $this->getSQL ( $sql ) ;
		if ($o = $result->fetch_object()) return $o->id ;

		# Create new
		$sql = "INSERT IGNORE INTO `users` (`name`) VALUES ('{$username}')" ;
		$this->getSQL ( $sql ) ;
		return $this->dbt->insert_id ;
	}

	public function get_or_create_query_id ( string $sparql , string $description , int $user_id ) : int {
		$sparql = $this->escape ( $sparql ) ;
		$sql = "SELECT `id` FROM `queries` WHERE `sparql`='{$sparql}'" ;
		$result = $this->getSQL ( $sql ) ;
		if ($o = $result->fetch_object()) return $o->id ;

		# Create new
		$ts = $this->tfc->getCurrentTimestamp() ;
		$description = $this->escape ( $description ) ;
		$sql = "INSERT IGNORE INTO `queries` (`description`,`sparql`,`user_id`,`timestamp_created`) VALUES ('{$description}','{$sparql}',{$user_id},'{$ts}')" ;
		$this->getSQL ( $sql ) ;
		return $this->dbt->insert_id ;
	}

	public function get_or_create_qs_command_id ( string $command , int $query_id , string $status = 'OPEN' ) : int {
		$command = $this->escape ( $command ) ;
		$sql = "SELECT `id` FROM `qs_commands` WHERE `command`='{$command}'" ;
		$result = $this->getSQL ( $sql ) ;
		if ($o = $result->fetch_object()) return $o->id ;

		# Create new
		$status = $this->escape ( $status ) ;
		$ts = $this->tfc->getCurrentTimestamp() ;
		$main_item = 'null' ; # TODO
		$main_property = 'null' ; # TODO
		if ( preg_match('/^Q(\d+)\|/',$command,$m) ) $main_item = $m[1] ;
		if ( preg_match('/^.+?\|P(\d+)\|/',$command,$m) ) $main_property = $m[1] ;
		$sql = "INSERT IGNORE INTO `qs_commands` (`command`,`main_item`,`main_property`,`from_query`,`status`,`timestamp_created`,`random`) VALUES ('{$command}',{$main_item},{$main_property},{$query_id},'{$status}','{$ts}',rand())" ;
		$this->getSQL ( $sql ) ;
		return $this->dbt->insert_id ;
	}

	public function run_query ( int $query_id ) : void {
		$sql = "SELECT * FROM `queries` WHERE `id`={$query_id}" ;
		$result = $this->getSQL ( $sql ) ;
		if ($o = $result->fetch_object()) {}
		else throw new Exception("No query #{$query_id}") ;
		$j = $this->tfc->getSPARQL ( $o->sparql ) ;
		if ( !isset($j) or !isset($j->head) ) throw new Exception("SPARQL query malfunction") ;
		if ( !isset($j->results) or !isset($j->results->bindings) or count($j->results->bindings) == 0 ) return ; # No results
		$varname = $j->head->vars[0] ;
		foreach ( $j->results->bindings AS $v ) {
			$command = $v->$varname->value ;
			$this->get_or_create_qs_command_id ( $command , $query_id , 'OPEN' ) ;
		}

		# Update query times
		$last_ts = $this->tfc->getCurrentTimestamp() ;
		$next_ts = date ( 'YmdHis' , strtotime('+'.$o->hours_between_runs.' hours') ) ;
		$sql = "UPDATE `queries` SET `last_run`='{$last_ts}',`next_run`='{$next_ts}',`status`='OK' WHERE `id`={$query_id}" ;
		$this->getSQL ( $sql ) ;
	}

	public function get_commands ( int $batch_size = 0 , int $query_id = 0 , int $main_item = 0 , int $main_property = 0) {
		$r = rand()/getrandmax();
		$sql = "SELECT * FROM `qs_commands` WHERE `status`='OPEN'" ;
		if ( $batch_size>0 ) $sql .= " AND `random`>={$r}" ;
		if ( $query_id>0 ) $sql .= " AND `from_query`={$query_id}" ;
		if ( $main_item>0 ) $sql .= " AND `main_item`={$main_item}" ;
		if ( $main_property>0 ) $sql .= " AND `main_property`={$main_property}" ;
		if ( $batch_size>0 ) $sql .= " ORDER BY `random` LIMIT {$batch_size}" ;
		$result = $this->getSQL ( $sql ) ;
		$ret = [] ;
		while ($o = $result->fetch_object()) {
			$o->command_array = str_getcsv ( $o->command , '|' ) ;
			$ret[] = $o ;
		}
		return $ret ;
	}

	public function get_query_batch ( int $start , int $batch_size , int $user_id = 0 ) {
		$ret = [] ;
		$sql = "SELECT `queries`.*, `users`.`name` AS `user_name` FROM `queries`,`users` WHERE `user_id`=`users`.`id`" ;
		if ( $user_id > 0 ) $sql .= " AND `user_id`={$user_id}" ;
		$sql .= " ORDER BY `timestamp_created` DESC LIMIT {$batch_size} OFFSET {$start}" ;
		$result = $this->getSQL ( $sql ) ;
		while ($o = $result->fetch_object()) $ret[] = $o ;
		return $ret ;
	}

	public function get_qs() {
		if ( !isset($this->tfc->qs) ) {
			$this->tfc->getQS ( 'quest' , $this->conf_file ) ;
			$this->tfc->qs->logging = false ;
		}
		return $this->tfc->qs ;
	}

	public function run_command ( int $command_id ) : string {
		$sql = "SELECT `command` FROM `qs_commands` WHERE `id`={$command_id}" ;
		$result = $this->getSQL ( $sql ) ;
		$commands = [] ;
		while ($o = $result->fetch_object()) $commands[] = $o->command ;
		if ( count($commands) == 0 ) throw new Exception("No command #{$command_id}") ;
		$this->get_qs() ;
		$q = $this->tfc->runCommandsQS ( $commands ) ;
		return $q ;
	}

	public function set_command_status ( int $command_id , string $status , string $username ) {
		$user_id = $this->get_or_create_user_id ( $username ) ;
		$status = $this->escape ( $status ) ;
		$ts = $this->tfc->getCurrentTimestamp() ;
		$sql = "UPDATE `qs_commands` SET `status`='{$status}',`user_id_decision`={$user_id},`timestamp_decision`='{$ts}' WHERE `id`={$command_id}" ;
		$this->getSQL ( $sql ) ;
	}

	public function save_query ( object $query ) : int {
		foreach ( ['description','sparql','user_id','hours_between_runs'] AS $k ) {
			if ( !isset($query->$k) ) throw new Exception("'{$k}' is required in the query") ;
			if ( trim($query->$k) == '' ) throw new Exception("'{$k}' must not be empty") ;
		}
		$query->last_run = '' ;
		$query->next_run = '' ;
		$query->status = 'OK' ;
		$query->timestamp_created = $this->tfc->getCurrentTimestamp() ;
		$keys = [] ;
		$values = [] ;
		if ( $query->id == 0 ) unset ( $query->id ) ;
		foreach ( $query AS $k => $v ) {
			$keys[] = "`".$this->escape("{$k}")."`" ;
			$values[] = "'".$this->escape("{$v}")."'" ;
		}
		if ( isset($query->id) and $query->id*1>0 ) {
			# Update
			$query->id *= 1 ;
			$sql = [] ;
			foreach ( $keys AS $num => $k ) $sql[] = "{$k}={$values[$num]}" ;
			$sql = "UPDATE `queries` SET " . implode(',',$sql) . " WHERE `id`={$query->id}" ;
			$this->getSQL ( $sql ) ;
			return $query->id ;
		} else {
			# Create
			$sql = "INSERT IGNORE INTO `queries` (" . implode(',',$keys) . ") VALUES (" . implode(',',$values) . ")" ;
			$this->getSQL ( $sql ) ;
			return $this->dbt->insert_id ;
		}
	}

	public function update_query_status ( int $query_id , string $new_status ) {
		$new_status = $this->escape ( $new_status ) ;
		$sql = "UPDATE `queries` SET `status`='{$new_status}' WHERE `id`={$query_id}" ;
		$this->getSQL ( $sql ) ;
	}

	public function get_query_by_id ( int $query_id ) : object {
		$sql = "SELECT * FROM `queries` WHERE `id`={$query_id}" ;
		$result = $this->getSQL ( $sql ) ;
		if ($o = $result->fetch_object()) return $o ;
		throw new Exception("No query #{$query_id}") ;
	}

	public function delete_query ( int $query_id ) {
		$sql = "DELETE FROM `qs_commands` WHERE `from_query`={$query_id}" ;
		$this->getSQL ( $sql ) ;
		$sql = "DELETE FROM `queries` WHERE `id`={$query_id}" ;
		$this->getSQL ( $sql ) ;
	}

	public function run_overdue_queries () {
		$ts = $this->tfc->getCurrentTimestamp() ;
		$sql = "SELECT * FROM `queries` WHERE `next_run`<='{$ts}' AND `status`='OK' ORDER BY `next_run`" ;
		$result = $this->getSQL ( $sql ) ;
		while ($o = $result->fetch_object()) {
			try {
				$this->run_query ( $o->id ) ;
			} catch(Exception $e) {
				$this->update_query_status($o->id,'FAILED');
			}
		}
	}

	protected function get_normalized_username ( string $username ) : string {
		$username = str_replace ( '_' , ' ' , $username ) ;
		$username = ucfirst(trim($username)) ;
		return $username ;
	}

	protected function getSQL ( string $sql ) {
		return $this->tfc->getSQL ( $this->dbt , $sql ) ;
	}

	protected function escape ( string $s ) : string {
		return $this->dbt->real_escape_string ( $s ) ;
	}
}


?>