<?php

namespace app\Database;

use PDO;
use Exception;
use PDOStatement;

class DatabaseHandler
{

    /**
     * Add a new record to the database
     *
     * @param array $parameters
     * @param bool $debug
     * @return array
     */
    protected function add(array $parameters, bool $debug = false): array
    {

        if( !empty($_SESSION) ){
            $parameters[1]['ip'] = $_SERVER['REMOTE_ADDR'];
            $parameters[1]['conta_id'] = $_SESSION['conta_id'];
            $parameters[1]['usuario_id'] = $_SESSION['usuario_id'];
        }

        try {

            $PDO = $this->Connect();

            $query = $this->insert($parameters, $debug);

            $PDO->query($query);

            return $this->get([ $parameters[0], 'a.*', ['id' => $PDO->lastInsertId() ], ['start' => 0, 'limit' => 1] ], $debug) ?? [[]];

        } catch (Exception $e) {

            var_dump($e);
            die();

        }

    }

    /**
     * Update some data in the database
     *
     * @param array $parameters
     * @param bool $debug
     * @return array|array[]|void
     */
    protected function alter( array $parameters, bool $debug = false )
    {

        if( !empty($_SESSION) ){
            $parameters[1]['ip'] = $_SERVER['REMOTE_ADDR'];
            $parameters[1]['conta_id'] = $_SESSION['conta_id'];
            $parameters[1]['usuario_id'] = $_SESSION['usuario_id'];
        }

        try {

            $PDO = $this->Connect();

            $PDO->prepare( $this->update($parameters, $debug) )->execute();

            return $this->get([$parameters[0], 'a.*', $parameters[2], ['start' => 0, 'limit' => 1]], $debug) ?? [];

        } catch (Exception $e) {

            var_dump($e);
            die();

        }

    }

    /**
     * Collect a dataset and create a formatted listing
     *
     * @param array $parameters
     * @param bool $debug
     * @return array
     */
    protected function list(array $parameters, bool $debug = false): array
    {

        $query = $this->get($parameters, $debug);

        $list = [];

        foreach ( $query ?? [] as $value ){
            $key = $value[array_key_first($value)];
            $val = $value[array_key_last($value)];
            $list[$key] = $val;
        }

        return $list;

    }

    /**
     * Collect a dataset
     *
     * @param array $parameters
     * @param bool $debug
     * @return array
     */
    protected function get(array $parameters, bool $debug = false): array
    {

        $parameters = [
            'table' => $parameters[0] ?? "",
            'column' => $parameters[1] ?? "a.*",
            'filter' => $parameters[2] ?? [],
            'limit' => $parameters[3] ?? [],
            'joins' => $parameters[4] ?? [],
            'order' => $parameters[5] ?? [],
            'group' => $parameters[6] ?? []
        ];

        try {

            $Request = $this->Connect()->query($this->select($parameters, $debug));

            $Request = $this->fetch($Request);

            if( empty( $Request ) ){
                return array( 0 => [] );
            }

            return $Request;

        } catch (Exception $e) {

            var_dump($e);
            die();

        }

    }

    /**
     * Count the number of records
     *
     * @param array $parameters
     * @param bool $debug
     * @return int
     */
    protected function count(array $parameters, bool $debug = false): int
    {

        $parameters = [
            'table' => $parameters[0] ?? "",
            'column' => "count(a.id) as count",
            'filter' => $parameters[1] ?? [],
            'joins' => $parameters[2] ?? []
        ];

        try {

            $Request = $this->Connect()->query($this->select($parameters, $debug));

            $Request = $this->fetch($Request);

            return (int)$Request[0]['count'] ?? 0;

        } catch (Exception $e) {

            var_dump($e);
            die();

        }

    }

    /**
     * Create a database connection or
     * validate an existing one
     *
     * @return PDO
     */
    private function connect(): PDO
    {

        try {

            $DSN = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $Connect = new PDO($DSN, DB_USERNAME, DB_PASSWORD);

            $Connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (Exception) {

            die();

        }

        try {

            $Connect->query("SELECT 1");

        } catch (Exception) {

            $this->connect();

        }

        return $Connect;

    }

    /**
     * Create the query needed to perform the
     * operation on the database
     *
     * @param array $props
     * @param bool $debug
     * @return string
     */
    private function select(array $props, bool $debug = false): string
    {

        //Start
        $query = 'select';
        $query .= " $props[column] from $props[table] ";

        if( str_contains($props['table'], "as a") === false ){
            $query .= " as a ";
        }

        //Join
        $query .= "";
        foreach ($props['joins'] ?? array() as $value) {
            $query .= " $value ";
        }

        //Filters
        $query .= $this->condition($props['filter']);

        //Group
        foreach ($props['group'] ?? array() as $group) {
            $query .= " $group ";
        }

        //Order
        $query .= " order by " . (!empty($props['order']) ? implode(" , ", $props['order']) : "a.id desc") . " ";

        //Limit
        if (!empty($props['limit'])) {

            $start = (int)($props["limit"]["start"] ?? 0);
            $limit = (int)($props["limit"]["limit"] ?? 0);

            if( $limit !== 0 ){
                $query .= " limit $start, $limit ";
            }

        }

        //Debug
        if ($debug) {
            var_dump($query);
            var_dump($props);
            die();
        }

        return trim($query) ?? "";


    }

    /**
     * Create filter conditions for a query
     * @param array $props
     * @return string
     */
    private function condition( array $props = [] ):string{

        //Filters
        $query = ' where a.id >= 1 ';

        foreach ($props ?? array() as $key => $value) {

            switch (trim($key)) {

                case str_starts_with($key, 'likeAfter ') :
                    $key = str_replace('likeAfter', '', $key);
                    $query .= "AND $key like '$value%' ";
                    break;

                case str_starts_with($key, 'likeBefore ') :
                    $key = str_replace('likeBefore', '', $key);
                    $query .= "AND $key like '%$value' ";
                    break;

                case str_starts_with($key, 'like or ') :
                    $close = "";
                    if (str_ends_with(trim($key), ')')) {
                        $key = str_replace(')', '', $key);
                        $close = ')';
                    }
                    $key = str_replace('like or ', '', $key);
                    $query .= "OR $key like '%$value%' $close";
                    break;

                case str_starts_with($key, 'notlike ') :
                    $key = str_replace('notlike ', '', $key);
                    $query .= "AND $key NOT LIKE '$value' ";
                    break;

                case str_starts_with($key, 'like ') :
                    $key = str_replace('like', '', $key);
                    $query .= "AND $key like '%$value%' ";
                    break;

                case str_starts_with($key, 'or ') :
                    $key = str_replace('or ', '', $key);
                    $query .= "OR $key = '$value' ";
                    break;

                case str_starts_with($key, 'in ') :
                    $key = str_replace('in ', '', $key);
                    $query .= "AND $key in $value ";
                    break;

                case str_starts_with(trim($key), 'is ') :
                    $key = str_replace('is ', '', $key);
                    $query .= "AND $key IS $value ";
                    break;

                case str_starts_with(trim($key), 'notin ') :
                    $key = str_replace('notin ', '', $key);
                    $query .= "AND $key not in $value ";
                    break;

                case str_starts_with($key, 'dif ') :
                    $key = str_replace('dif', '', $key);
                    $query .= "AND $key != '$value' ";
                    break;

                case str_starts_with($key, '<=') :
                    $key = str_replace('<=', '', $key);
                    $query .= "AND $key <= $value ";
                    break;

                case str_starts_with($key, '>=') :
                    $key = str_replace('>=', '', $key);
                    $query .= "AND $key >= $value ";
                    break;

                case str_starts_with($key, '<') :
                    $key = str_replace('<', '', $key);
                    $query .= "AND $key < $value ";
                    break;

                case str_starts_with($key, '>') :
                    $key = str_replace('>', '', $key);
                    $query .= "AND $key > $value ";
                    break;

                case str_starts_with($key, 'sql') :
                    $query .= " $value ";
                    break;

                case str_starts_with(trim($key), 'findOr') :

                    $key = str_replace('findOr', '', $key);

                    $queries = "";
                    $wheres = explode(',', $value);

                    if (!empty($wheres)) {

                        foreach ($wheres as $k => $vl) {

                            if ($k === array_key_first($wheres)) {
                                $queries .= 'AND ';
                            }

                            $queries .= 'FIND_IN_SET ("' . $vl . '", ' . $key . ')';

                            if ($k !== array_key_last($wheres)) {
                                $queries .= ' OR ';
                            }

                        }

                    }

                    $query .= $queries;

                    break;

                case str_starts_with(trim($key), 'findAnd') :

                    $key = str_replace('findAnd', '', $key);

                    $queries = "";
                    $wheres = explode(',', $value);

                    if (!empty($wheres)) {

                        foreach ($wheres as $k => $vl) {

                            if ($k === array_key_first($wheres)) {
                                $queries .= 'AND ';
                            }

                            $queries .= 'FIND_IN_SET ("' . $vl . '", ' . $key . ')';

                            if ($k !== array_key_last($wheres)) {
                                $queries .= ' AND ';
                            }

                        }

                    }

                    $query .= $queries;

                    break;

                default :
                    $query .= "AND $key = '$value' ";
                    break;
            }

        }

        return $query;
    }

    /**
     *
     * Create the query needed to perform the
     * operation on the database
     *
     * @param array $props
     * @param bool $debug
     * @return string
     */
    private function insert(array $props, bool $debug = false):string{

        $column = '';
        $values = '';

        unset($props[1]['id']);

        foreach ($props[1] ?? [] as $key => $prop) {
            $column .= "," . json_encode($key);
            $values .= ", " . json_encode((string)$prop, JSON_UNESCAPED_UNICODE);
        }

        $column = str_replace('"', "`", $column);

        //Start
        $query = 'insert '.'into '.$props[0].' ( `id` '.$column.') values ( NULL '.$values.' );';

        if ($debug) {
            var_dump($query);
            var_dump($props);
            die();
        }

        return trim($query) ?? "";

    }

    /**
     * Create the query needed to perform the
     * operation on the database
     * @param array $props
     * @param bool $debug
     * @return string
     */
    private function update(array $props, bool $debug = false):string{

        $values = '';

        foreach ($props[1] ?? [] as $key => $prop) {

            if( $key === 'id' ){
                continue;
            }

            $values .= " `".$key."` = " . json_encode((string)$prop, JSON_UNESCAPED_UNICODE) . ",";
        }

        $values = substr(trim($values) ?? "", 0, -1);

        //Start
        $condition = trim(str_replace('where' , "", $this->condition($props[2])));
        $query = 'update '.$props[0].' as a set '.$values.' where ( '.$condition.' );';

        if ($debug) {
            var_dump($query);
            var_dump($props);
            die();
        }

        return trim($query) ?? "";

    }

    /**
     * Fetches the remaining rows from a result set
     *
     * @param PDOStatement $Statement
     * @return array
     */
    private function fetch(PDOStatement $Statement): array
    {

        $fetchAll = $Statement->fetchAll(PDO::FETCH_ASSOC);

        if( is_array( $fetchAll ) ){

            foreach ( $fetchAll as $key => $fetch ) {

                unset(
                    $fetchAll[$key]['ip'],
                    $fetchAll[$key]['usuario_id'],
                    $fetchAll[$key]['conta_id']
                );

            }

        }

        unset(
            $fetchAll['ip'],
            $fetchAll['usuario_id'],
            $fetchAll['conta_id']
        );

        return $fetchAll;

    }

}