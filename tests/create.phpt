--TEST--
pquery.create - basic test for pQuery CREATE
--FILE--
<? require 'test-setup.php';
$pdo = new pdo('sqlite::memory:');
$test = pquery('test_table', $pdo);
$test->create(array(
	'id' => 'INT AUTO_INCREMENT',
	'username' => 'VARCHAR(32)',
	'email' => 'TEXT'
))->query();

var_dump($test->columns());

?>
--EXPECT--
string(32) "# hello All, I sAid hi planet! #"
