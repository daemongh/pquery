# Introduction
pQuery is a PHP 5.2+ utility library for writing and executing PDO
queries via a fluent interface; similar to how jQuery accesses the
DOM with CSS selectors.

Methods in pQuery don't perform operatoins until you call query().
Most methods are chainable and return a new context. This allows
you to build queries in pieces, re-using common JOINs, field
descriptors, conditions, etc.

# Basic Usage
To get an instance you use a global factory method:

	function pquery ( ... )

It can take the following arguments in any order:

* string:  table to use
* pdo:     a PDO instance to use

Here is an example of working with the 'cart_orders' table:

	$orders = pquery('order_table', $pdo);

You may not need to pass the $pdo object if pquery has been
configured with a base, like this:

	pquery::base($pdo); // Set default PDO object

Once you have a context you can start building
more complex queries:

	$orders = pquery('order_table', $pdo);
	$find_order = $orders->limit(1)->where(array('id' => $user_id));

No SQL queries have been ran yet. It's also important to remember
that with pquery the order of operations for building queries doesn't
matter. Nothing is ran until you invoke query(), like this:

	$order = $find_order->query()->fetch();

Here query() returns the PDO statement and we get a row using fetch()
like normal. Overall it can now be written:

	$order = $orders->
		limit(1)->
		where(array('id' => $user_id))->
		query()->
	fetch();

This shows that pquery is good at building queries with minimal syntax,
but also good at building complex dynamic queries.

You can also avoid large try/catch blocks by:

	$findOrder = .. long query here ..
	
	try {
	   $order = $findOrder->query();
	}

All the operations so far have implicitly been SELECT statements for
all columns, eg. 'SELECT * FROM foo' - however pquery has syntax support
for:

* CREATE
* INSERT
* SELECT
* DELETE
* UPDATE

Without syntax support you can use the query() method passing strings
to be used as SQL text and arrays to bind data:

	$statement = $orders->query("SHOW COLUMNS LIKE", array("%$search%"));

Each argument will be joined with a space, so the example would become:

	SHOW COLUMNS LIKE ?

And a single paramter would be passed to the prepared statement. If you
want to pass a parameter without the ? being put in it's place you can
use named keys:

	$orders->query(
	  "SELECT * FROM foo WHERE x=:search OR x LIKE :search",
	  array('search' => $search)
	)->fetch();



