--TEST--
pquery.select - basic test for pQuery select SQL
--FILE--
<? require 'test-setup.php';

echo pquery('test'), "\n";
echo pquery('test')->select('one'), "\n";
echo pquery('test')->select('one', 'two'), "\n";
echo pquery('test')->select(array(
	'alpha' => 'one',
	'beta' => 'two'
)), "\n";


?>
--EXPECT--
SELECT * FROM `test`
SELECT `one` FROM `test`
SELECT `one`,`two` FROM `test`
SELECT `one` AS `alpha`,`two` AS `beta` FROM `test`
