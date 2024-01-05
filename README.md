# Mysqli2 v1.6.2

Mysqli Abstraction Layer v1.6.1

Description:
Mainly for development and logging of queries, but now that the class is up and running future releases should be expected to do the heavy lifting of queries and iteration.

Maintained by: @steinhaug

## Version history

### v1.6.2

- Updated for PHP 8.1  

## Install by composer

To install the library use composer:

    composer require steinhaug/mysqli

## Init 

We want this to be a replacement for the existing $mysqli function in PHP so initialize your DB connection, using credentials from credentials.php in project.

    $mysqli = Mysqli2::getInstance($mysql_host, $mysql_port, $mysql_user, $mysql_password, $mysql_database);

    if ($mysqli->connect_errno) {
        echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
    }

    if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
        exit();
    }

