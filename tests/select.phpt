--TEST--
pquery.select - basic test for pQuery select
--FILE--
<? require 'test-setup.php';

$pdo = new pdo('sqlite::memory:');
$test = pquery('test_table', $pdo);

$test->create(array(
	'id' => 'primary int auto_increment',
	'username' => 'varchar(32)',
	'email' => 'text'
))->query();

$test->insert(array(
	'username' => 'kris',
	'email' => 'kristopher.ives@gmail.com'
))->query();

print_r($test->select()->query());
?>
--EXPECT--
string(32) "# hello All, I sAid hi planet! #"
