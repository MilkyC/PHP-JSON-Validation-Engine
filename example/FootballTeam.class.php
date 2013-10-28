<?php

	class FootballTeam extends JSONValidator{
		private static $schema;

        public static function getInstance($rawData){
        	self::$schema = array(
	            'required' => array(
	                'roster' => array(
	                    'required' => array(
	                    	'positions' => array(
	                    		'required'=>array('position'=>1, 'last_name' => 1, 'number' => 1,),
	                    		'optional' => array('first_name' => function($data){print_r($data);}, 'age' => 1),
								'info' => array('multi_array' => true)
	                    	)
	                    ),
	                    
	           			'info' => array('multi_array' => false)
	                ),
	                'coaching_staff' => array(
	                    'required' => array( 'last_name' => 1, 'coaching_position' => 1),
	                    'optional' => array('first_name' => 1, 'age' => 1),
	           			'info' => array('multi_array' => true)
	                ),
	                'stadium'=>1, 'logo'=>1
	            ),
	            'optional' => array('mascot'=>function($data){print_r($data);}, 'jumbotron'=>1),
	            'info' => array('multi_array' => false)
	        );
            self::$requiredValueMapping = self::$schema;
            return new self($rawData);
        }

	}