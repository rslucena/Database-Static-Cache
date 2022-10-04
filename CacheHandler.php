<?php

namespace app\Cache;

use app\Database\DatabaseHandler;
use Exception;

class CacheHandler extends DatabaseHandler
{

    //Action
    protected string $command = "";
    protected array $action = [];

    //Files
    protected array $path = [];
    protected string $key = "";
    protected int $time = 10;

    //Database
    protected string $table;
    protected mixed $values = [];
    protected string $column = 'a.*';
    protected array $group = [];
    protected array $order = [];
    protected array $join = [];
    protected array $limit = [ 'start' => 0, 'limit' => 15 ];
    protected array $filter = [];

    /**
     * Get content key cache
     * @return static
     */
    static public function make(): static
    {
        return new static();
    }

    //Set properties

    /**
     * Format the call structure for database access
     * @param $command
     * @return $this
     */
    public function command($command): static
    {

        switch ($command) {

            case "add" :
            {
                $this->action = [$this->table, $this->values, true];
                break;
            }

            case "alter" :
            {
                $this->action = [$this->table, $this->values, $this->filter];
                break;
            }

            case "disable" :
            {
                $this->action = [$this->table, $this->filter];
                break;
            }

            case "get" :
            {
                $this->action = [$this->table, $this->column, $this->filter, $this->limit, $this->join, $this->order, $this->group];
                break;
            }

            case "list" :
                $this->action = [ $this->table, $this->column, $this->filter, $this->limit, $this->join, $this->order, $this->group ];
                break;

            case "count" :
            {
                $this->action = [$this->table, $this->filter, $this->join];
                break;
            }

            case "mock":
                $this->action = [$this->table, $this->column, $this->filter, $this->limit, $this->order, $this->group];
            break;

            default:
                break;
        }

        $this->command = $command;

        return $this;
    }

    /**
     * Set time cache file
     * @param int $minutes
     * @return static
     */
    public function time(int $minutes = 10): static
    {
        $this->time = $minutes;
        return $this;
    }

    /**
     * Set table cache file
     * @param mixed $table
     * @return static
     */
    public function table(mixed $table): static
    {

        if( is_array($table) ){
            $table = implode("," , $table);
        }

        $this->table = $table;
        return $this;
    }

    /**
     * Set column cache file
     * @param string $column
     * @return static
     */
    public function column(string $column = '*'): static
    {
        $this->column = $column;
        return $this;
    }

    /**
     * Set column cache file
     * @param array $join
     * @return static
     */
    public function join(array $join = []): static
    {
        $this->join = $join;
        return $this;
    }

    /**
     * Define a pagination for collecting information
     * @param array $limit
     * @return static
     */
    public function limit(array $limit = []): static
    {

        $start = isset($limit[0]) ? (int)$limit[0] : 0;
        $limit = isset($limit[1]) ? (int)$limit[1] : 15;

        $this->limit = [
            'start' => $start,
            'limit' => $limit
        ];

        if( $this->limit['limit'] === -1 ){
            unset($this->limit['limit']);
        }

        if( $this->limit['start'] === -1 ){
            $this->limit = [];
        }

        return $this;
    }

    /**
     * Set content order
     * @param array $orderby
     * @return static
     */
    public function order(array $orderby = []): static
    {
        $this->order = $orderby;
        return $this;
    }

    /**
     * Set content grouping
     * @param array $groupby
     * @return static
     */
    public function group(array $groupby = []): static
    {
        $this->group = $groupby;
        return $this;
    }

    /**
     * Define filter cache
     * @param array $filter
     * @return mixed
     */
    public function filter(array $filter): static
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Define values cache
     * @param array $values
     * @return mixed
     */
    public function values(array $values): static
    {
        $this->values = $values;
        return $this;
    }

    //Handle cache files

    /**
     * Clear cache key
     * @return void
     */
    public function wipe():void
    {

        $this->file();

        $path = $this->path['dir'] ?? __DIR__ . DIRECTORY_SEPARATOR . $this->table . DIRECTORY_SEPARATOR;

        if(empty($path) ){
            return;
        }

        $files = glob($path . '*.cache');

        foreach ($files ?? [] as $file){
            unlink($file);
        }

    }

    /**
     * Load values in cache
     * @param bool $debug
     * @return mixed
     */
    public function load( bool $debug = false ): mixed
    {

        $this->file();
        $command = $this->command;

        if( $debug === true ){
            $this::$command($this->action, $debug);
        }

        if ($this->path['file_exists']) {

            $this->values = file_get_contents($this->path['filename']);

            //$this->values = openssl_decrypt($this->values, CACHE_CIPHER, CACHE_KEY, 0, CACHE_IV);

            $content = json_decode($this->values, true) ?? array();

            $limit = $content['limit_time'] ?? 0;

            if ( $limit < time() === true) {
                $this->path['file_exists'] = false;
            }

        }

        if ( $this->path['file_exists'] === false ) {

            try {

                $this->values = $this::$command($this->action);

                if( $this->path['need_wipe'] && !empty($this->values[0]['id']) ){

                    $this->command = 'get';
                    $this->action[1] = $this->column;
                    $this->action[2] = [ 'a.id' => $this->values[0]['id'] ];
                    $this->action[3] = $this->limit;

                    $this->file();
                    $this->wipe();

                }

                $this->values = $this->set($this->values);

            } catch (Exception) {
            }

        }

        return json_decode($this->values, true)['cache'] ?? "";

    }

    public function mock( bool $debug = false ) : mixed {

        $this->limit([-1,-1]);
        $this->command('mock');
        $this->file();

        if( $debug ){
            var_dump($this->command);
            var_dump($this->action);
            var_dump($this->path);
            die();
        }

        $values = "";

        if ( $this->path['file_exists'] === true ) {

            $values = file_get_contents($this->path['filename']) ?? "";

            $content = json_decode($values, true);

            $limit = $content['limit_time'] ?? 0;

            if ( $limit < time() === true) {
                $this->path['file_exists'] = false;
            }

            if( empty($content['cache'] ) ){
                $this->path['file_exists'] = false;
            }

        }

        if( $this->path['file_exists'] === false ){

            $values = '{"cache":{}}';

            if( !empty($this->values) ){
                $values = $this->set($this->values, true);
            }

        }

        return json_decode($values, true)['cache'] ?? "";

    }

    /**
     * Get infopath file
     */
    private function file(): void
    {

        $hash = "";

        foreach ($this->action as $props) {

            if( !is_array($props) ){
                $hash .= "$props";
                continue;
            }

            foreach ($props ?? array() as $key => $prop ){
                $hash .= "-$key-$prop";
            }

        }

        $hash = $this->slug(str_replace($this->table, "", $this->slug($hash) ?? ""));

        if(!empty($hash)){
            $hash = '-' . $hash;
        }

        $this->path['need_wipe'] = $this->command === 'add' || $this->command === 'alter';

        $this->path['key'] = $this->table;
        $this->path['dir'] = __DIR__ . DIRECTORY_SEPARATOR . $this->table . DIRECTORY_SEPARATOR;

        $hash = $this->command . $hash;
        $hash = hash_hmac('sha256', $hash , 'CACHEMODE');
        $this->path['filename'] = $this->path['dir'] . $hash . ".cache";

        $this->path['dir_exists'] = is_dir($this->path['dir']);
        $this->path['file_exists'] = file_exists($this->path['filename']);

    }

    /**
     * Reform a string
     * @param string $text
     * @return string
     */
    private function slug(string $text): string
    {

        $text = preg_replace('~[^\pL\d]+~u', "-", $text);

        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        $text = preg_replace('~[^-\w]+~', '', $text);

        $text = trim($text, "-");

        $text = preg_replace('~-+~', "-", $text);

        $text = strtolower($text);

        if (empty($text)) {
            return '';
        }

        return $text;
    }

    /**
     * Set value in key cache
     * @param mixed $values
     * @param mixed $debug
     * @return string
     */
    private function set(mixed $values, bool $debug = false ): string
    {

        $limit_time = $this->time !== 0 ? strtotime("+$this->time minutes", time()) : 0;

        $file = json_encode([
            'cache' => $values,
            'limit_time' => $limit_time
        ], JSON_UNESCAPED_UNICODE);

        if( $limit_time === 0 ){
            return $file;
        }

        try {

            if ($this->path['dir_exists'] === false) {
                mkdir($this->path['dir'], recursive: true);
            }

            //file_put_contents($this->path['filename'], openssl_encrypt($file, CACHE_CIPHER, CACHE_KEY, 0, CACHE_IV));
            file_put_contents($this->path['filename'], $file);

        } catch (Exception) {
        }

        return $file;

    }

}