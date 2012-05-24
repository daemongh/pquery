--TEST--
pquery.join - basic test for pQuery join SQL
--FILE--
<? require 'test-setup.php';

echo pquery('orders'), "\n";
echo pquery('orders')->join('users', 'user_id'), "\n";
echo pquery('orders')->join('users', array('user_id' => 'id')), "\n";
echo pquery('orders')->
	join('users', 'user_id')->
	join('events', 'order_id'), "\n";
echo pquery('orders')->
	join(array(
		'users' => 'user_id',
		'events' => 'order_id'
	)), "\n";
?>
--EXPECT--
SELECT * FROM `orders`
SELECT * FROM `orders` LEFT JOIN `users` ON (`orders`.`user_id` = `users`.`user_id`)
SELECT * FROM `orders` LEFT JOIN `users` ON ( `orders`.user_id = `users`.`id` )
SELECT * FROM `orders` LEFT JOIN `users` ON (`orders`.`user_id` = `users`.`user_id`) LEFT JOIN `events` ON (`orders`.`order_id` = `events`.`order_id`)
SELECT * FROM `orders` LEFT JOIN `users` ON (`orders`.`user_id` = `users`.`user_id`) LEFT JOIN `events` ON (`orders`.`order_id` = `events`.`order_id`)
