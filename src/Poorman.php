<?php

namespace Daison\LaravelExperimental;

use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * A poorman implementation of multiple connections in just 1 query.
 *
 * @author Daison Carino <daison12006013@gmail.com>
 */
class Poorman
{
    /**
     * The database query build used.
     *
     * @var Builder
     */
    protected $query;

    /**
     * The closure passed under query.
     *
     * @var \Closure
     */
    protected $queryClosure;

    /**
     * Lists of all connections available.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * The base model to use.
     *
     * @var string
     */
    protected $model;

    /**
     * __call
     *
     * @param mixed $method
     * @param mixed $params
     * @return void
     */
    public function __call($method, $params)
    {
        return call_user_func_array([$this->builder, $method], $params);
    }

    /**
     * newTable
     *
     * @param mixed $connections
     * @param mixed $query
     * @return void
     */
    protected function newTable($connections, $query)
    {
        $raw = $this->getUnionQuery($connections, $query);

        # wrap the raw qery into a new expression table
        $wrapped = DB::raw(sprintf('(%s) as aggregated_tables', $raw));

        # transform the wrapper table into a database table
        $table = DB::table($wrapped);

        # apply the same query on the last level
        call_user_func_array($this->queryClosure, [&$table]);

        return $table;
    }

    /**
     * getUnionQuery
     *
     * @return void
     */
    protected function getUnionQuery($connections, $query)
    {
        if (!$connections) {
            return $this->getBuilderSql(clone $query);
        }

        $ret = '';

        foreach ($connections as $connection) {
            $baseRawQuery = $this->getBuilderSql(
                (clone $query)->selectSub("'{$connection}'", 'database_connection')
            );

            $replacedRawQuery = $this->replaceConnection($connection, $baseRawQuery);

            if ($ret) {
                $ret = "{$ret} union all ({$replacedRawQuery})";
            } else {
                $ret = "({$replacedRawQuery})";
            }
        }

        return $ret;
    }

    /**
     * replaceConnection
     *
     * @param mixed $connection
     * @param mixed $raw
     * @return void
     */
    protected function replaceConnection($connection, $raw)
    {
        $table = (new $this->model)->getTable();

        return str_replace(
            "`{$table}`",
            "`{$connection}`.`{$table}`",
            $raw
        );
    }

    /**
     * Get the raw sql of the query.
     *
     * @param mixed $query
     * @return string
     */
    protected function getBuilderSql($query)
    {
        # first escape the custom percent in the builder
        $str = str_replace('%', '%%', $query->toSql());

        # then replace all ? into %s
        $str = str_replace(['?'], ['\'%s\''], $str);

        # now pass in the bindings
        return vsprintf($str, $query->getBindings());
    }

    /**
     * toRawSql
     *
     * @return void
     */
    public function toRawSql()
    {
        return $this->getBuilderSql($this->builder);
    }

    /**
     * query
     *
     * @param  string   $model
     * @param  \Closure $queryClosure
     * @return Poorman
     */
    public function query($model, \Closure $queryClosure)
    {
        $this->model        = $model;
        $this->queryClosure = $queryClosure;

        $builder = (new $model)->newQuery();
        call_user_func_array($queryClosure, [&$builder]);

        # set the initial builder
        $this->builder = $this->newTable(
            $this->connections,
            $builder
        );

        return $this;
    }

    /**
     * connections
     *
     * @param ... $connections
     * @return Poorman
     */
    public function connections(...$connections)
    {
        $lists = [];

        foreach ($connections as $connection) {
            if (is_array($connection)) {
                $lists = array_merge($lists, $connection);
            } else {
                $lists[] = $connection;
            }
        }

        $this->connections = $lists;

        return $this;
    }
}
