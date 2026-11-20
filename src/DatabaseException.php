<?php

class DatabaseException extends Exception {
    private $sqlQuery;
    private $sqlError;
    private $sqlErrno;
    
    /**
     * @param string $error Raw MySQL error (getSqlError())
     * @param string $query
     * @param int $errno
     * @param string|null $message Exception message, defaults to $error
     */
    public function __construct($error, $query = '', $errno = 0, $message = null) {
        $this->sqlQuery = $query;
        $this->sqlError = $error;
        $this->sqlErrno = $errno;
        parent::__construct($message ?? $error, (int)$errno);
    }
    
    public function getSqlQuery() { return $this->sqlQuery; }
    public function getSqlError() { return $this->sqlError; }
    public function getSqlErrno() { return $this->sqlErrno; }
}
