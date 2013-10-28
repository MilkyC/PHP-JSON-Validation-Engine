<?php
	require('../JSONValidator.class.php');
	require('./FootballTeam.class.php');
	


	$json = '{"roster":{"positions": [{"position":"quarterback", "last_name":"Montana", "first_name":"joe", "number":16}]}, "coaching_staff":[], "mascot":"Niner", "stadium":"Candlestick", "logo":"SF"}';
	$json_arr = json_decode($json, true);
	$testObj = FootballTeam::getInstance($json_arr);

	var_dump($testObj); exit;