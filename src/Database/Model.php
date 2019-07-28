<?php

namespace Bellona\Database;

use Bellona\Support\Facades\DB;
use PDO;

class Model
{
    /** @var QueryBuilder $query QueryBuilder instance. */
    private $query;

    /** @var string $table Name of database table corrseponding to the model. */
    protected $table;

    /** @var string $table Name of primary key in table. */
    protected $primaryKey = 'id';

    /** @var array $fillable List of fields in the database table which queries can insert/update.
     *
     * Defines which attributes can be assigned with the Model::assign() method,
     * preventing invalid post data being assigned and saved to database.
     */
    protected $fillable = [];


    /**
     * Save object as a record in the database.
     *
     * @return bool True if record successfully updated/inserted; false otherwise.
     */
    public function save()
    {
        if (isset($this->{$this->primaryKey})) {
            return $this->update();
        }
        return $this->insert();
    }


    /**
     * Save object as a new record in the database base on its primary key.
     *
     * @return bool True if record successfully inserted; false otherwise.
     */
    protected function insert()
    {
        $attributes = $this->getAttributes();

        if ($result = DB::table($this->table)->insert($attributes)) {
            $this->{$this->primaryKey} = $result;
            return true;
        }

        return false;
    }


    /**
     * Update existing record in the database base on its primary key.
     *
     * @return bool True if record updated or change had no effect; false otherwise.
     */
    protected function update()
    {
        $attributes = $this->getAttributes();

        $primaryKey = $this->primaryKey;

        $result = DB::table($this->table)->where($primaryKey, $this->$primaryKey)->limit(1)->update($attributes);
        return $result !== false;
    }


    /**
     * Delete record from database base on its primary key.
     *
     * @return bool True if record successfully deleted; false otherwise.
     */
    public function delete()
    {
        $primaryKey = $this->primaryKey;
        return (bool)DB::table($this->table)->where($primaryKey, $this->$primaryKey)->limit(1)->delete();
    }


    /**
     * Get associative array of all attributes on the current object
     * which correspond to a fillable field in the database table.
     *
     * @return array Array of fillable attributes.
     */
    protected function getAttributes()
    {
        $attributes = [];
        foreach ($this->fillable as $field) {
            if (property_exists($this, $field)) {
                $attributes[$field] = $this->$field;
            }
        }
        return $attributes;
    }


    /**
     * Assign values from an associative array or object to this model instance,
     * only if they correspond to a fillable field in the model.
     *
     * @return object Model instance with values assigned.
     */
    public function assign($values)
    {
        foreach ($values as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->$key = $value;
            }
        }
        return $this;
    }


    /**
     * Retrieve name of primary key.
     *
     * @return string Primary key for the model.
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }


    /**
     * Retrieve record(s) from database by their primary key
     * and return as (a) new model instance(s).
     *
     * @return mixed Record(s)
     */
    private function find($values)
    {
        if (!is_array($values)) {
            return $this->query->where($this->primaryKey, $values)->first(PDO::FETCH_CLASS, static::class);
        }
        $orWhere = [];
        foreach ($values as $value) {
            $orWhere[] = [$this->primaryKey, $value];
        }
        return $this->query->orWhere($orWhere)->get(PDO::FETCH_CLASS, static::class);
    }


    /**
     * Insert a record into the database.
     *
     * @return mixed Primary key of last inserted record, or false if insert failed.
     */
    private function create(array $values)
    {
        $vals = array_filter($values, function ($key) {
            return in_array($key, $this->fillable, true);
        }, ARRAY_FILTER_USE_KEY);
        if ($key = $this->query->insert($vals)) {
            return DB::table($this->table)->where($this->primaryKey, $key)->first();
        }
        return false;
    }


    /**
     * Delete record(s) from the database.
     *
     * @return int Number of records deleted.
     */
    private function destroy($values)
    {
        if (!is_array($values)) {
            return $this->query->where($this->primaryKey, $values)->limit(1)->delete();
        }
        $orWhere = [];
        foreach ($values as $value) {
            $orWhere[] = [$this->primaryKey, $value];
        }
        return $this->query->orWhere($orWhere)->limit(count($values))->delete();
    }


    /**
     * Assume calls to non-existent or private functions
     * are meant for calls to the query builder.
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, ['get', 'first'])) {
            return $this->query->$name(\PDO::FETCH_CLASS, static::class);
        }
        $this->query->$name(...$arguments);
        return $this;
    }


    /**
     * Assume calls to static functions are meant for the query
     * builder; instantiate new model, set new query builder,
     * and pass function call through to new model instance.
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = (new static);
        $instance->query = DB::table($instance->table);
        return $instance->$name(...$arguments);
    }
}
