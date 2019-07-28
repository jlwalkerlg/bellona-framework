<?php

namespace Bellona\Database;

use PDO;
use PDOStatement;

class QueryBuilder
{
    /** @var PDO $dbh Database connection handle. */
    private $dbh;

    /** @var PDOStatement $sth PDO statement handle. */
    private $sth;

    /** @var string $table Name of table to query. */
    private $table;

    /** @var string $sql SQL query string. */
    private $sql = '';

    /** @var array $params Parameters to bind to the prepared statement. */
    private $params = [];

    /** @var array $select Array of columns to SELECT. */
    private $select = ['*'];

    /** @var array $where Array of WHERE clauses. */
    private $where = [];

    /** @var int $limit LIMIT. */
    private $limit;

    /** @var int $offset OFFSET. */
    private $offset;

    /** @var array $orderBy Array of ORDER BY statements. */
    private $orderBy = [];

    /** @var string $orderDirection ORDER BY direction. */
    private $orderDirection;

    /** @var array $joins Array of JOIN statements. */
    private $joins = [];

    /** @var array $validOperators List of valid operators allow in query. */
    private $validOperators = ['=', '<', '>', '!=', '<>', '<=>', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE'];


    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }


    /**
     * Prepare raw query on database.
     *
     * Statement handle returned so error info can be retrieved etc.
     * Will have to be executed manually.
     *
     * @param string $sql SQL query string
     * @param array $params params to bind to prepared statement
     * @return PDOStatement PDO statement instance.
     */
    public function query(string $sql, array $params = [])
    {
        if (empty($params)) {
            $sth = $this->dbh->query($sql);
            return $sth;
        }

        $this->params = $params;
        $this->sth = $this->dbh->prepare($sql);
        $this->bind();

        return $this->sth;
    }


    /**
     * Begin transaction.
     */
    public function beginTransaction()
    {
        $this->dbh->beginTransaction();
    }


    /**
     * Commit transaction.
     */
    public function commit()
    {
        $this->dbh->commit();
    }


    /**
     * Rollback transaction.
     */
    public function rollback()
    {
        $this->dbh->rollBack();
    }


    /**
     * Wrap closure (anonymous function) in transaction.
     *
     * @param \Closure $callback
     * @return mixed Result of running the closure
     * @throws \Throwable Re-throws any exception or error that was caught.
     */
    public function transaction(\Closure $callback)
    {
        try {
            $this->beginTransaction();
            $result = $callback();
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
        return $result;
    }


    /**
     * Set name of table to query.
     *
     * @param string $table Name of table to query
     */
    public function table(string $table)
    {
        // If table name not valid, throw exception.
        if (!$this->filterTableName($table)) {
            throw new \Exception('Invalid table name.');
        }

        $this->table = $table;

        return $this;
    }


    /**
     * Store select statements on query builder object.
     *
     * @param string $columns Columns to grab from table(s)
     * @return QueryBuilder Same query builder
     */
    public function select(...$columns)
    {
        foreach ($columns as $select) {
            if (!$this->filterColumns(str_replace(' ', '', $select))) {
                return $this;
            }
        }
        $this->select = $columns;
        return $this;
    }


    /**
     * Add select part of query to query string.
     */
    private function buildSelect()
    {
        $columns = implode(', ', $this->select);
        $this->sql .= "SELECT $columns FROM $this->table";
    }


    /**
     * Store limit on query builder object.
     *
     * @param mixed $limit Limit
     * @return QueryBuilder Same query builder
     */
    public function limit($limit)
    {
        $limit = (int)$limit;
        $this->limit = $limit;
        $this->params[':limit'] = $limit;
        return $this;
    }


    /**
     * Add limit part of query to query string.
     */
    private function buildLimit()
    {
        if (is_null($this->limit)) return;

        $this->sql .= ' LIMIT :limit';
    }


    /**
     * Store offset on query builder object.
     *
     * @param mixed $offset Offset
     * @return QueryBuilder Same query builder
     */
    public function offset($offset)
    {
        $offset = (int)$offset;
        $this->offset = $offset;
        $this->params[':offset'] = $offset;
        return $this;
    }


    // Add offset part of query to query string.
    private function buildOffset()
    {
        if (is_null($this->offset)) return;

        $this->sql .= ' OFFSET :offset';
    }


    /**
     * Store AND WHERE statements on query builder object.
     *
     * @param array $wheres Array of AND WHERE statements
     * @return QueryBuilder Same query builder
     */
    public function where(...$wheres)
    {
        $this->storeWheres('AND', ...$wheres);
        return $this;
    }


    /**
     * Store OR WHERE statements on query builder object.
     *
     * @param array $wheres Array of OR WHERE statements
     * @return QueryBuilder Same query builder
     */
    public function orWhere(...$wheres)
    {
        $this->storeWheres('OR', ...$wheres);
        return $this;
    }


    /**
     * Store generic arrays of WHERE statements on query builder object.
     *
     * @param array $wheretype Type of WHERE statement (AND/OR/NOT)
     * @param mixed $params Conditions for the WHERE statement
     */
    private function storeWheres(string $wheretype, ...$params)
    {
        if (is_array($params[0])) {
            foreach ($params[0] as $row) {
                $row[] = $wheretype;
                $this->storeWhere($row);
            }
        } else {
            $params[] = $wheretype;
            $this->storeWhere($params);
        }
    }


    /**
     * Store generic WHERE array on query builder object.
     *
     * @param mixed $params Conditions for the WHERE statement
     */
    private function storeWhere(array $params)
    {
        $field = $params[0];
        if (count($params) > 3) {
            $operator = $params[1];
            $value = $params[2];
            $andornot = $params[3];
        } else {
            $operator = '=';
            $value = $params[1];
            $andornot = $params[2];
        }

        // Bail if operator not valid.
        if (!in_array($operator, $this->validOperators, true)) return;

        // Make sure a parameter of the same field name does not
        // already exist in the params table, otherwise the same value
        // will be bound for both of them.
        $paramName = $this->uniqParamName($field);
        $this->where[] = [$field, $operator, $value, $paramName, $andornot];
        $this->params[$paramName] = $value;
    }


    /**
     * Add WHERE part of query to query string.
     */
    private function buildWhere()
    {
        if (empty($this->where)) return;

        $sql = '';
        foreach ($this->where as $clause) {
            $field = $clause[0];
            $operator = $clause[1];
            $paramName = $clause[3];
            $andornot = $clause[4];
            $sql .= "$andornot $field $operator $paramName ";
        }
        $sql = trim($sql, ' NOT ');
        $sql = trim($sql, ' AND ');
        $sql = trim($sql, ' OR ');
        $sql = ' WHERE ' . $sql;
        $this->sql .= $sql;
    }


    /**
     * Store ORDER BY array on query builder object.
     *
     * @param mixed $columns Column(s) to order results by
     * @param string $direction Direction in which to order results
     */
    public function orderBy($columns, string $direction)
    {
        // Ensure directions is a valid direction, otherwise bail.
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            return $this;
        }

        // Convert $columns to an array.
        $columns = is_array($columns) ? $columns : [$columns];

        // Filter column names through regex pattern as a safety precaution.
        // Note: NOT 100% safe.
        if (!$this->filterColumns(...$columns)) {
            return $this;
        }

        $this->orderDirection = $direction;
        $this->orderBy = $columns;

        return $this;
    }


    /**
     * Add order by part of query to query string.
     */
    private function buildOrderBy()
    {
        if (empty($this->orderBy)) return;

        $this->sql .= ' ORDER BY ' . implode(',', $this->orderBy) . ' ' . $this->orderDirection;
    }


    /**
     * Store INNER JOIN statement on query builder object.
     *
     * @param string $table Table to join with
     * @param string $table1col Column from first table to use in join condition
     * @param string $operator Operator to join the two columns by
     * @param string $table2col Column from second table to use in join condition
     */
    public function join(string $table, string $table1col, string $operator, string $table2col)
    {
        $this->addJoin('inner', $operator, $table, $table1col, $table2col);
        return $this;
    }


    /**
     * Store LEFT JOIN statement on query builder object.
     *
     * @param string $table Table to join with
     * @param string $table1col Column from first table to use in join condition
     * @param string $operator Operator to join the two columns by
     * @param string $table2col Column from second table to use in join condition
     */
    public function leftJoin(string $table, string $table1col, string $operator, string $table2col)
    {
        $this->addJoin('left', $operator, $table, $table1col, $table2col);
        return $this;
    }


    /**
     * Store RIGHT JOIN statement on query builder object.
     *
     * @param string $table Table to join with
     * @param string $table1col Column from first table to use in join condition
     * @param string $operator Operator to join the two columns by
     * @param string $table2col Column from second table to use in join condition
     */
    public function rightJoin(string $table, string $table1col, string $operator, string $table2col)
    {
        $this->addJoin('right', $operator, $table, $table1col, $table2col);
        return $this;
    }


    /**
     * Store generic JOIN statement on query builder object.
     *
     * @param array $data Join parameters
     */
    private function addJoin(...$data)
    {
        $type = strtoupper(array_shift($data));
        $operator = array_shift($data);
        if (!$this->filterOperators($operator)) return;
        if (!$this->filterColumns(...$data)) return;
        array_unshift($data, $type, $operator);
        $this->joins[] = $data;
    }


    /**
     * Add join part of query to query string.
     */
    private function buildJoin()
    {
        if (empty($this->joins)) return;

        foreach ($this->joins as $join) {
            $type = $join[0];
            $operator = $join[1];
            $table = $join[2];
            $table1col = $join[3];
            $table2col = $join[4];

            $this->sql .= " $type JOIN $table ON $table1col $operator $table2col";
        }
    }


    /**
     * Query database and retrieve all results.
     *
     * @param string $model Optional name of class to instantiate returned records as.
     * @return array Array of results as model instances, or stdClass objects.
     */
    public function get(int $fetchType = PDO::FETCH_OBJ, string $model = null)
    {
        $this->buildSelect();
        $this->buildJoin();
        $this->buildWhere();
        $this->buildOrderBy();
        $this->buildLimit();
        $this->buildOffset();
        $this->execute();
        if (isset($model)) {
            return $this->sth->fetchAll($fetchType, $model);
        }
        return $this->sth->fetchAll($fetchType);
    }


    /**
     * Query database and retrieve first result.
     *
     * @param string $model Optional name of class to instantiate returned records as.
     * @return object Result as a model instance, or stdClass object.
     */
    public function first(int $fetchType = PDO::FETCH_OBJ, string $model = null)
    {
        $this->limit(1);
        $results = $this->get($fetchType, $model);
        return array_shift($results);
    }


    /**
     * Count results based on the query.
     *
     * @return int Count returned from query
     */
    public function count()
    {
        $this->select = ['COUNT(*)'];
        $this->buildSelect();
        $this->buildJoin();
        $this->buildWhere();
        $this->buildLimit();
        $this->buildOffset();
        $this->execute();
        return (int)$this->sth->fetchColumn();
    }


    /**
     * Insert values into database table.
     *
     * @param array $fillables Array of column=>value pairs to fill in table
     * @return mixed Primary key of last inserted value, or false if no values were inserted
     */
    public function insert(array $fillables)
    {
        $attributes = [];
        if (is_array(reset($fillables))) {
            foreach ($fillables as $fillable) {
                $attributes[] = $fillable;
            }
        } else {
            $attributes[] = $fillables;
        }

        $fields = array_keys($attributes[0]);

        $this->sql = "INSERT INTO $this->table (";
        $this->sql .= implode(',', $fields);
        $this->sql .= ') VALUES ';
        for ($i = 0; $i < count($attributes); $i++) {
            $this->sql .= '(';
            foreach ($fields as $field) {
                $paramName = ':' . $field . $i;
                $this->sql .= $paramName . ',';
                $this->params[$paramName] = $attributes[$i][$field];
            }
            $this->sql = rtrim($this->sql, ',') . '),';
        }
        $this->sql = rtrim($this->sql, ',');

        $this->execute();

        if ($this->sth->rowCount()) {
            return $this->dbh->lastInsertId();
        }
        return false;
    }


    /**
     * Update record(s) in database table.
     *
     * @param $values Array of field=>value pairs to update
     * @return int Number of records updated
     */
    public function update(array $values)
    {
        $this->sql = "UPDATE $this->table SET ";
        foreach ($values as $column => $value) {
            $paramName = $this->uniqParamName($column);
            $this->params[$paramName] = $value;
            $this->sql .= "$column=$paramName,";
        }
        $this->sql = rtrim($this->sql, ',');
        $this->buildWhere();
        $this->buildLimit();
        $this->buildOffset();
        $this->execute();

        return $this->sth->rowCount();
    }


    /**
     * Delete record(s) from database table.
     *
     * @return int Number of records deleted
     */
    public function delete()
    {
        $this->sql = "DELETE FROM $this->table ";
        $this->buildWhere();
        $this->buildLimit();
        $this->buildOffset();
        $this->execute();

        return $this->sth->rowCount();
    }


    /**
     * Execute query.
     */
    private function execute()
    {
        if (empty($this->params)) {
            return $this->sth = $this->dbh->query($this->sql);
        } else {
            $this->sth = $this->dbh->prepare($this->sql);
            $this->bind();
            return $this->sth->execute();
        }
    }


    /**
     * Ensure parameter name is not already in params array.
     *
     * Used when binding params so the same value is not bound to two separate params.
     *
     * @return string Unique parameter name
     */
    private function uniqParamName($paramName)
    {
        $paramName = strpos($paramName, ':') === 0 ? $paramName : ':' . $paramName;
        $paramName = str_replace('.', '_', $paramName);
        $i = 1;
        while (array_key_exists($paramName, $this->params)) {
            $paramName = $paramName . $i;
        }
        return $paramName;
    }


    /**
     * Bind all parameters to statement handle as appropriate type.
     */
    private function bind()
    {
        foreach ($this->params as $paramName => $value) {
            $type = $this->getPdoType($value);
            // If using positional (?) placeholders, param names are
            // integerers; ensure they are one-based (required by PDO).
            if (is_int($paramName)) $paramName++;;
            $this->sth->bindValue($paramName, $value, $type);
        }
    }


    /**
     * Get PDO type for variable.
     *
     * @return string PDO type.
     */
    private function getPdoType($var)
    {
        switch (gettype($var)) {
            case 'boolean':
                $type = PDO::PARAM_BOOL;
                break;
            case 'integer':
                $type = PDO::PARAM_INT;
                break;
            case 'null':
                $type = PDO::PARAM_NULL;
                break;
            default:
                $type = PDO::PARAM_STR;
        }
        return $type;
    }



    /**
     * Simple filter to reduce risk of SQL injection.
     * Used for table names, which can not
     * bound by prepared statements.
     * Note: NOT SAFE. If dynamic data is allowed for SQL indicators
     * (e.g. table/column names), it should be filtered through a whitelist first.
     *
     * @return bool True if table name passes the filter; false otherwise.
     */
    private function filterTableName($name)
    {
        $pattern = '/^[A-Za-z_ ]+$/';
        return preg_match($pattern, $name);
    }


    /**
     * Simple filter to reduce risk of SQL injection.
     * Used for column names, which can not
     * bound by prepared statements.
     * Note: NOT SAFE. If dynamic data is allowed for SQL indicators
     * (e.g. table/column names), it should be filtered through a whitelist first.
     *
     * @return bool True if column name passes the filter; false otherwise.
     */
    private function filterColumns(...$columns)
    {
        $pattern = '/^[A-Za-z_\*\.\(\) ]+$/';
        foreach ($columns as $column) {
            if (!preg_match($pattern, $column)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Filter operators through the valid operators whitelist.
     *
     * @return bool True if all operators are in whitelist; false otherwise.
     */
    private function filterOperators(...$operators)
    {
        foreach ($operators as $operator) {
            $operator = strtoupper($operator);
            if (!in_array($operator, $this->validOperators)) {
                return false;
            }
        }
        return true;
    }
}
