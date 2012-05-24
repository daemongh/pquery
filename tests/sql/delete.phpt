--TEST--
pquery.delete - basic test for pQuery delete SQL
--FILE--
<? require 'test-setup.php';

echo pquery('test')->delete(), "\n";
echo pquery('test')->
	delete()->
	where(array('thing' => true)), "\n";
?>
--EXPECT--
DELETE `test`
DELETE `test` WHERE ( ( `thing` = ? ) )
