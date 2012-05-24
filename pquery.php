<? /* pQuery 1.0 - http://github.com/krisives/pquery */

function pquery() {
	$args = func_get_args();
	$params = pquery::$base;
	
	foreach ($args as $arg) {
		pquery::configure($arg, $params);
	}
	
	return new pquery($params);
}

class pquery {
	protected $parent;
	protected $attrs;
	
	public function __construct($attrs, $parent=null) {
		$this->attrs = isset($attrs) ? $attrs : array();
		$this->parent = $parent;
	}
	
	public function __call($func, array $args) {
		if (!array_key_exists($func, self::$resolvers)) {
			if (!array_key_exists(strtolower($func), self::$resolvers)) {
				throw new Exception("Unknown pQuery function '$func'");
			}
		}
		
		return new pquery(array($func => $args), $this);
	}
	
	public function __get($key) {
		$list = array();
		$node = $this;
		
		do {
			if (array_key_exists($key, $node->attrs)) {
				array_unshift($list, $node->attrs[$key]);
			}
			
			$node = $node->parent;
		} while ($node);
		
		return $list;
	}
	
	public function __toString() {
		return implode(' ', $this->tokenize());
	}
	
	public function tokenize(&$queryParams=null, &$sql=null) {
		if (!isset($sql)) { $sql = array(); }
		$node = $this;
		$lookup = array();
		
		do {
			foreach ($node->attrs as $key => $value) {
				$lookup[$key] = 1;
			}
			
			$node = $node->parent;
		} while ($node);
		
		if (!isset($queryParams)) {
			$queryParams = array();
		}
		
		$cmd = null;
		
		foreach (array('select', 'update', 'insert', 'delete', 'create') as $key) {
			if (array_key_exists($key, $lookup)) {
				if (isset($cmd)) { throw new Exception("Cannot mix '$key' and '$cmd'"); }
				$cmd = $key;
			}
		}
		
		if (!isset($cmd)) {
			$cmd = 'select';
		}
		
		foreach (self::$resolvers as $key => $resolver) {
			if ($key != $cmd && !array_key_exists($key, $lookup)) {
				continue;
			}
			
			if (is_string($resolver)) {
				$this->$resolver($sql, $queryParams);
			} else {
				$resolver($this, $sql, $queryParams);
			}
		}
		
		return $sql;
	}
	
	public function columns() {
		$list = array();
		$pdo = $this->pdo[0];
		$result = null;
		
		switch ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
		case 'sqlite':
			$result = $this->query('PRAGMA table_info(', $this->resolveKey($this->table), ')');
			break;
		default:
			$result = $this->query('SHOW COLUMNS', $this->resolveKey($this->table));
			break;
		}
		
		return $result->fetchAll();
	}
	
	public function query() {
		$sql = func_get_args();
		$queryParams = array();
		$sql = implode(' ', $this->tokenize($queryParams, $sql));
		
		foreach ($this->pdo as $pdo) {
			$statement = $pdo->prepare($sql);
			
			if (!$statement) {
				throw new PDOException("Unable to prepare SQL");
			}
			
			$statement->execute($queryParams);
		}
		
		if (empty($statement)) {
			throw new PDOException("No PDO object to use");
		}
		
		return $statement;
	}
	
	protected function resolveSelect(&$sql, &$queryParams) {
		$sql[] = 'SELECT';
		$fields = $this->select;
		
		if (empty($fields)) {
			$sql[] = '*';
		} else {
			$sql[] = $this->resolveKey($fields);
		}
		
		$sql[] = 'FROM';
		$sql[] = $this->resolveKey($this->table);
	}
	
	protected function resolveUpdate(&$sql, &$queryParams) {
		$sql[] = 'UPDATE';
		$sql[] = $this->resolveKey($this->table);
		$sql[] = 'SET';
		
		foreach ($this->update as $update) {
			$this->resolveUpdateClause($update, $sql, $queryParams);
		}
	}
	
	protected function resolveUpdateClause($update, &$sql, &$queryParams) {
		$sep = 0;
		
		foreach ($update as $arg) {
			if (!is_array($arg)) {
				if ($sep) { $sql[] = ','; }
				$sql[] = $arg;
				$sep = 1;
				continue;
			}
			
			foreach ($arg as $key => $value) {
				if ($sep) { $sql[] = ','; }
				$sql[] = $this->resolveKey($key);
				$sql[] = '= ?';
				$queryParams[] = $value;
				$sep = 1;
			}
		}
	}
	
	protected function resolveJoin(&$sql, &$queryParams) {
		$joins = $this->join;
		if (empty($joins)) return;
		$localTable = $this->resolveKey($this->table);
		
		foreach ($joins as $join) {
			$this->resolveJoinClause($localTable, $join, $sql, $queryParams);
		}
	}
	
	protected function resolveJoinClause($localTable, $join, &$sql, &$queryParams) {
		if (is_array($join[0])) {
			foreach ($join[0] as $foreignTable => $on) {
				$this->resolveJoinClause($localTable, array($foreignTable, $on), $sql, $queryParams);
			}
			
			return;
		}
		
		$foreignTable = $this->resolveKey($join[0]);
		$sql[] = 'LEFT JOIN';
		$sql[] = $foreignTable;
		$sql[] = 'ON';
		
		$on = $join[1];
		
		if (is_string($on)) {
			$on = $this->resolveKey($on);
			$sql[] = "($localTable.$on = $foreignTable.$on)";
			return;
		}
		
		$op = 'AND';
		$sql[] = '(';
		$sep = 0;
		
		foreach ($on as $localKey => $foreignKey) {
			if (is_numeric($localKey)) {
				if ($this->resolveOperator($value, $op)) {
					continue;
				}
				
				if ($sep) { $sql[] = $op; }
				$sql[] = $value;
				$sep = 1;
				continue;
			}
			
			if ($sep) { $sql[] = $op; }
			$foreignKey = $this->resolveKey($foreignKey);
			$sql[] = "$localTable.$localKey = $foreignTable.$foreignKey";
			$sep = 1;
		}
		
		$sql[] = ')';
	}
	
	protected function resolveOrder(&$sql, &$queryParams) {
		$sep = 0;
		
		foreach ($this->order as $call) {
			$sort = 'ASC';
			
			foreach ($call as $arg) {
				if ($this->resolveOperator($arg, $sort, self::$orderDirections)) {
					continue;
				}
				
				if (!$sep) {
					$sql[] = 'ORDER BY';
				}
				
				if ($sep) {
					$sql[] = $sort;
					$sql[] = ',';
				}
				
				$sql[] = $this->resolveKey($arg);
				$sep = 1;
			}
		}
		
		if ($sep) {
			$sql[] = $sort;
		}
	}
	
	protected function resolveLimit(&$sql, &$queryParams) {
		$length = null;
		$start = null;
		
		foreach ($this->limit as $limit) {
			if (count($limit) == 1) {
				$length = $limit[0];
			} else if (count($limit) == 2) {
				$start = $limit[0];
				$length = $limit[1];
			}
		}
		
		if (!isset($length) && !isset($start)) return;
		$sql[] = 'LIMIT';
		
		if (!empty($start)) {
			$this->resolveExpr($start, $sql, $queryParams);
			$sql[] = ',';
		}
		
		$this->resolveExpr($length, $sql, $queryParams);
	}
	
	protected function resolveWhere(&$sql, &$queryParams) {
		$where = $this->where;
		if (empty($where)) return;
		$sql[] = 'WHERE';
		$sep = 0;
		
		foreach ($where as $arg) {
			if ($sep) $sql[] = 'AND';
			$this->resolveWhereCondition($arg, $sql, $queryParams);
			$sep = 1;
		}
	}
	
	protected function resolveWhereCondition($args, &$sql, &$queryParams) {
		if (empty($args)) return;
		$sql[] = '(';
		$op = 'AND';
		$sep = 0;
		
		foreach ($args as $arg) {
			if (is_string($arg)) {
				if ($this->resolveOperator($arg, $op)) {
					continue;
				}
			}
			
			if ($sep) $sql[] = $op;
			$this->resolveExpr($arg, $sql, $queryParams);
			$sep = 1;
		}
		
		$sql[] = ')';
	}
	
	protected function resolveExpr($value, &$sql, &$queryParams) {
		if (is_numeric($value)) {
			$sql[] = $value;
			return;
		}
		
		if (is_array($value)) {
			$this->resolveExprArray($value, $sql, $queryParams);
			return;
		}
		
		$sql[] = $value;
	}
	
	protected function resolveExprArray($array, &$sql, &$queryParams) {
		if (empty($array)) {
			return;
		}
		
		$op = 'AND';
		$sql[] = '(';
		$sep = 0;
		
		foreach ($array as $key => $value) {
			if (is_numeric($key)) {
				if ($this->resolveOperator($value, $op)) {
					continue;
				}
				
				if ($sep) { $sql[] = $op; }
				$sql[] = $value;
				$sep = 1;
				continue;
			}
			
			if ($sep) { $sql[] = $op; }
			$sql[] = "`$key` = ?";
			$queryParams[] = $value;
			$sep = 1;
		}
		
		$sql[] = ')';
	}
	
	protected function resolveOperator($key, &$op, $from=null) {
		if (!isset($from)) {
			$from = self::$operators;
		}
		
		if (array_key_exists($key, $from)) {
			$op = $from[$key];
			return true;
		}
		
		$key = strtoupper($key);
		
		if (array_key_exists($key, $from)) {
			$op = $from[$key];
			return true;
		}
		
		return false;
	}
	
	protected function resolveKey($value) {
		if (is_array($value)) {
			$array = $value;
			
			foreach ($array as $key => $value) {
				if (is_numeric($key)) {
					$array[$key] = $this->resolveKey($value);
				} else {
					$array[$key] = $this->resolveKey($value)." AS `$key`";
				}
			}
			
			return implode(',', $array);
		}
		
		if ($value == '*') { return '*'; }
		if (strpos($value, '(') !== false) { return $value; }
		return "`$value`";
	}
	
	protected function resolveCreate(&$sql, &$queryParams) {
		$sql[] = 'CREATE TABLE IF NOT EXISTS';
		$sql[] = $this->resolveKey($this->table);
		$sql[] = '(';
		$sep = 0;
		
		foreach ($this->create as $call) {
			foreach ($call as $arg) {
				if (is_array($arg)) {
					foreach ($arg as $key => $value) {
						if ($sep) { $sql[] = ','; }
						$sep = 1;
						$sql[] = $this->resolveKey($key);
						$sql[] = $value;
					}
					
					continue;
				}
				
				if ($sep) { $sql[] = ','; }
				$sep = 1;
				$sql[] = $arg;
			}
		}
		
		$sql[] = ')';
	}
	
	public function resolveDelete(&$sql, &$queryParams) {
		$sql[] = 'DELETE';
		$sql[] = $this->resolveKey($this->table);
	}
	
	public static $resolvers = array(
		'create' => 'resolveCreate',
		'insert' => 'resolveInsert',
		'select' => 'resolveSelect',
		'update' => 'resolveUpdate',
		'delete' => 'resolveDelete',
		'join' => 'resolveJoin',
		'where' => 'resolveWhere',
		'group' => 'resolveGroup',
		'order' => 'resolveOrder',
		'limit' => 'resolveLimit'
	);
	
	public static $operators = array(
		'OR' => 'OR',
		'AND' => 'AND',
		'&&' => 'AND',
		'||' => 'OR',
		'XOR' => 'XOR'
	);
	
	public static $orderDirections = array(
		'DESC' => 'DESC',
		'ASC' => 'ASC'
	);
	
	public static $base = array();
	
	public static function base() {
		foreach (func_get_args() as $arg) {
			self::configure($arg, self::$base);
		}
	}
	
	public static function configure($arg, &$params) {
		if ($arg instanceof pdo) {
			$arg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$arg->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			$params['pdo'] = $arg;
			return;
		}
		
		if (is_object($arg)) {
			$arg = (array)$arg;
		}
		
		if (is_array($arg)) {
			foreach ($arg as $key => $value) {
				$params[$key] = $value;
			}
			
			return;
		}
		
		if (is_string($arg)) {
			$params['table'] = $arg;
		}
	}
}



