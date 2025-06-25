<?php

class DatabaseException extends Exception {
    private $sqlQuery;
    private $sqlError;
    private $sqlErrno;
    
    public function __construct($message, $query = '', $errno = 0) {
        $this->sqlQuery = $query;
        $this->sqlError = $message;
        $this->sqlErrno = $errno;
        parent::__construct($message, $errno);
    }
    
    public function getSqlQuery() { return $this->sqlQuery; }
    public function getSqlError() { return $this->sqlError; }
    public function getSqlErrno() { return $this->sqlErrno; }
}
