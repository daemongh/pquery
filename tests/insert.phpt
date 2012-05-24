--TEST--
pquery.insert - basic test for pQuery INSERT
--FILE--
<? require 'test-setup.php';
$pdo = new pdo('sqlite::memory:');
$test = pquery('test_table', $pdo);

$test->create(array(
	'id' => 'primary int auto_increment',
	'username' => 'varchar(32)',
	'email' => 'text'
))->query();

var_dump($test->columns());
?>
--EXPECT--
string(32) "# hello All, I sAid hi planet! #"
