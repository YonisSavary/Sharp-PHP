<?php

namespace Sharp\Classes\Extras\AutobahnDrivers;

use Exception;
use Sharp\Classes\Core\Events;
use Sharp\Classes\Data\Database;
use Sharp\Classes\Data\DatabaseQuery;
use Sharp\Classes\Data\ObjectArray;
use Sharp\Classes\Extras\Autobahn;
use Sharp\Classes\Http\Request;
use Sharp\Classes\Http\Response;
use Sharp\Core\Utils;

class BaseDriver implements DriverInterface
{
    /**
     * @return array<[\Sharp\Classes\Data\Model,array]>
     */
    protected static function extractRequestData(Request $request)
    {
        $extras = $request->getRoute()->getExtras();

        $model = $extras["autobahn-model"] ?? null;
        $model = Autobahn::getInstance()->throwOnInvalidModel($model);

        $middlewares = $extras["autobahn-middlewares"] ?? [];

        return [$model, $middlewares];
    }

    public static function createCallback(Request $request): Response
    {
        list($model, $middlewares) = self::extractRequestData($request);

        $query = new DatabaseQuery($model::getTable(), DatabaseQuery::INSERT);

        $params = $request->all();

        foreach ($middlewares as $middleware)
            $middleware($params);

        $query->setInsertField(array_keys($params));
        $query->insertValues(array_values($params));

        $events = Events::getInstance();
        $events->dispatch("autobahnCreateBefore", ["model"=>$model, "query"=>$query]);

        $query->fetch();

        $inserted = Database::getInstance()->lastInsertId();
        $events->dispatch("autobahnCreateAfter", ["model"=>$model, "query"=>$query, "insertedId"=>$inserted]);

        return Response::json(["insertedId"=>$inserted], Response::CREATED);
    }

    public static function multipleCreateCallback(Request $request): Response
    {
        list($model, $middlewares) = self::extractRequestData($request);

        $data = $request->body();

        if (!is_array($data))
            return Response::json('Only Arrays or objects are allowed !', 400);

        $data = Utils::toArray($data);

        $fields = array_keys($data[0]);
        $badFields = array_diff($fields, $model::getFieldNames()) ;
        if (count($badFields))
            return Response::json("[$model] does not contains theses fields " . json_encode($badFields), 400);

        $query = new DatabaseQuery($model::getTable(), DatabaseQuery::INSERT);
        $query->setInsertField($fields);

        $data = ObjectArray::fromArray($data);
        foreach ($middlewares as $middleware)
            $data = $data->filter($middleware);

        $data->forEach(function($element) use (&$query) {
            $query->insertValues(array_values($element));
        });

        $events = Events::getInstance();
        $events->dispatch("autobahnMultipleCreateBefore", ["model"=>$model, "query"=>&$query]);

        $query->fetch();
        $lastInsert = Database::getInstance()->lastInsertId();

        $insertedIdList = range($lastInsert-$data->length()+1, $lastInsert);


        $events->dispatch("autobahnMultipleCreateAfter", ["model"=>$model, "query"=>&$query, "insertedId" => $insertedIdList]);

        return Response::json(['insertedId' => $insertedIdList]);
    }

    public static function readCallback(Request $request): Response
    {
        list($model, $middlewares) = self::extractRequestData($request);

        $query = new DatabaseQuery($model::getTable(), DatabaseQuery::SELECT);

        $doJoin = ($request->params("_join") ?? "true") === "true";
        $ignores = Utils::toArray($request->params("_ignores") ?? []);

        $request->unset(["_ignores", "_join"]);
        $query->exploreModel($model, $doJoin, $ignores);

        foreach ($request->all() as $key => $value)
        {
            if (is_array($value))
                $query->whereSQL("`$key` IN {}", [$value]);
            else
                $query->where($key, $value);
        }

        foreach ($middlewares as $middleware)
            $middleware($query);

        $events = Events::getInstance();
        $events->dispatch("autobahnReadBefore", ["model"=>$model, "query"=>&$query]);

        $results = $query->fetch();

        $events->dispatch("autobahnReadAfter", ["model"=>$model, "query"=>&$query, "results"=>$results]);

        return Response::json($results);
    }



    public static function updateCallback(Request $request): Response
    {
        list($model, $middlewares) = self::extractRequestData($request);

        if (!($primaryKey = $model::getPrimaryKey()))
            throw new Exception("Cannot update a model without a primary key");

        if (!($primaryKeyValue = $request->params($primaryKey)))
            return Response::json("A primary key [$primaryKey] is needed to update !", 401);

        $query = new DatabaseQuery($model::getTable(), DatabaseQuery::UPDATE);
        $query->where($primaryKey, $primaryKeyValue);

        foreach($request->all() as $key => $value)
        {
            if ($key === $primaryKey)
                continue;
            $query->set($key, $value);
        }

        foreach ($middlewares as $middleware)
            $middleware($query);

        $events = Events::getInstance();
        $events->dispatch("autobahnUpdateBefore", ["model"=>$model, "query"=>&$query]);

        $query->fetch();

        $events->dispatch("autobahnUpdateAfter", ["model"=>$model, "query"=>&$query]);

        return Response::json("DONE", Response::CREATED);
    }


    public static function deleteCallback(Request $request): Response
    {
        list($model, $middlewares) = self::extractRequestData($request);

        $query = new DatabaseQuery($model::getTable(), DatabaseQuery::DELETE);

        if (!count($request->all()))
            return Response::json("At least one filter must be given", Response::CONFLICT);

        foreach ($request->all() as $key => $value)
            $query->where($key, $value);

        foreach ($middlewares as $middleware)
            $middleware($query);

        $events = Events::getInstance();
        $events->dispatch("autobahnDeleteBefore", ["model"=>$model, "query"=>&$query]);

        $query->fetch();

        $events->dispatch("autobahnDeleteAfter", ["model"=>$model, "query"=>&$query]);

        return Response::json("DONE");
    }
}