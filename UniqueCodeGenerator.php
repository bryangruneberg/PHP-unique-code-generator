<?php

$options = array(
     'db_host'       => 'localhost',
     'db_name'       => 'somedsomedb',
     'db_user'       => 'someuser',
     'db_pass'       => 'somepass',
     'db_table'      => 'sometable',
     'charset'       => 'ACDEFGHJKMNPRTUVWXYZ234679'
 );

 $gen = new UniqueCodeGenerator($options);
 $gen->generate(
     1001000, // number of codes to generate
     9,     // length of codes
     dirname(__FILE__).'/out.csv' // output file
 );

/**
 * @class UniqueCodeGenerator
 * @author Darren Inwood, Chrometoaster New Media Ltd
 * Generates unique codes using a MySQL database.
 * More codes can be run if necessary in future, that are unique from the ones that have
 * already been generated.
 * Usage:
 * $gen = new UniqueCodeGenerator();
 * $gen->generate(51000, 8, dirname(__FILE__).'/out.csv');
 * This generator uses an inefficient uniqueness checking algorithm, which means as the 
 * number of codes increases, it takes longer to check whether it's unique.
 */

class UniqueCodeGenerator {

    /** Holds the SQLite database */
    private $db;

    /** Holds the options */
    private $options;

    /**
     * Constructor.
     */
    public function __construct($options=null) {
        // Set up options
        if ( ! is_array($options) ) {
            $options = array();
        }
        $defaults = $this->get_default_options();
        $this->options = array_merge( $defaults, $options );
        // Connect to db
        $this->connect();
    }

    /**
     * Generates unique codes and outputs to a file as CSV.
     * @param $count (Integer) Number of codes to generate.
     * @param $length (Integer) Character length of each code.
     * @param $file (String) Full file path to the output file. Make sure the 
     *          file exists and is writable, or the parent directory is writable.
     * @param $options (String) Options array.
     */
    public function generate($count, $length, $file) {

        // Can we generate this many keys?
        $sane = $this->sanity_check($count, $length);
        if ( ! $sane ) {
            return;
        }
        
        // Does the file exist?
        if ( file_exists($file) ) {
            echo "File $file exists, can't continue.\n\n";
            return;
        }
        $fp = fopen($file, 'w');
        if ( $fp == false ) {
            echo "Couldn't open file $file, can't continue.\n\n";
            return;
        }
        
        $existing = $this->count();
        $writes = 0;
        
        // Generate... use while loop so we keep going till we have enough codes
	$codes_left = $count - $existing;
        while ( $codes_left > 0 ) {
		print $codes_left . " left to generate and write \n";
		if($codes_left < 100000) {
		  $go_gen = $codes_left;
		} else {
		  $go_gen = 100000;
		}

	    $write_buffer = array();
	    for($i = 0; $go_gen > 0 && $i < $go_gen; $i++) {
              $new_code = $this->generate_code($length);
	      $write_buffer[] = $new_code;
	    }
	    foreach($write_buffer as $new_code) {
		    fputcsv($fp, array($new_code));
		    $sql = sprintf(
				    "INSERT INTO %s SET code = '%s'",
				    $this->options['db_table'],
				    $new_code
				  );
		    mysql_query($sql);
		    $writes++;
		    if ( $writes % 1000 == 0 ) {
			    echo "$writes codes...\n";
		    }
	    }
	   $codes_left = $count - $this->count();
        }
        fclose($fp);

        echo "\n$writes codes were generated.\n";
        echo "$count  codes written to file $file.\n\n";
        return;
    }


    /**
     * Returns the default options for the generator. Override any of these in the
     * $options parameter of the generate() function.
     * @return (Array) Associative array of default options.
     */
    private function get_default_options() {
        return array(
            'db_host'       => 'localhost',
            'db_name'       => 'databasename',
            'db_user'       => 'username',
            'db_pass'       => 'password',
            'db_table'      => 'databasetablename',
            'charset'       => '234679ACDEFGHJKLMNPQRTUVWXY'
        );
    }

    /**
     * Generates a random code of a given length.  Uses only characters in the 
     * charset, set in the $options array.
     * @param $length (Integer) Number of characters to put in the code.
     * @return (String) Randomly generated string.
     */
    private function generate_code($length) {
        $random= "";
        $data = $this->options['charset'];
        for($i = 0; $i < $length; $i++) {
            $random .= substr($data, (rand()%(strlen($data))), 1);
        }
        return $random;
    }

    /**
     * Does a sanity check, prints out some info to the console/screen, and returns
     * whether the request is sane or not.
     */
    private function sanity_check($count, $length) {
        // Outputs:
        // Possible generated codes: xxx
        // Possible secure codes: xxx
        // Existing codes: xxx
        // Can [not ]generate xxx new codes.
        $possible_total  = pow( strlen($this->options['charset']), $length );
        $a = log( $possible_total, 2 );
        $possible_secure = pow( 2, 0.5 * $a );
        echo 'Possible generated codes: '.$possible_total."\n";
        echo 'Possible secure codes: '.$possible_secure."\n";
        
        $current_codes = $this->count();
        echo 'Existing codes: '.$current_codes."\n";        
        
        $possible = true;
        if ( $possible_secure < $count ) {
            $possible = false;
        }
        echo 'Can '.($possible ? '' : 'not ').'generate '.$count.'  codes.'."\n";
        return $possible;
    }

    /**
     * Returns the number of codes curreently in the database.
     * @return (Integer) The current number of codes in the database.
     */
    private function count() {  
        $sql = sprintf("SELECT COUNT(*) AS count FROM %s", $this->options['db_table']);
        $current_codes = mysql_fetch_array(mysql_query($sql));
        return (int)$current_codes['count'];
    }

    /**
     * Conects to the database, stores the handle as $this->db.
     * If the table set in $options doesn't exist, creates it.
     */
    private function connect() {
        // Connect
        $this->db = mysql_connect(
            $this->options['db_host'],
            $this->options['db_user'],
            $this->options['db_pass']
        );
        // Create database if needed
        $sql = sprintf(
            "CREATE DATABASE IF NOT EXISTS %s",
            $this->options['db_name']
        );
        mysql_query($sql);
        mysql_select_db( $this->options['db_name'] );
        // Create table if needed
        $sql = sprintf(
            "CREATE TABLE IF NOT EXISTS %s ( "
            ."code VARCHAR(128) UNIQUE "
            .")",
            $this->options['db_table']
        );
        mysql_query($sql);
    }

}

?>
