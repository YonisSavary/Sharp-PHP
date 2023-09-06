<?php

namespace Sharp\Classes\Data;

use Exception;
use InvalidArgumentException;
use PDO;
use Sharp\Classes\Core\Logger;
use Sharp\Classes\Data\Classes\QueryCondition;
use Sharp\Classes\Data\Classes\QueryField;
use Sharp\Classes\Data\Classes\QueryJoin;
use Sharp\Classes\Data\Classes\QueryOrder;
use Sharp\Classes\Data\Classes\QuerySet;
use Sharp\Classes\Data\Classes\QueryConditionRaw;
use Sharp\Classes\Data\Database;
use Sharp\Core\Utils;

class DatabaseQuery
{
    const INSERT = 1;
    /** Alias of DatabaseQuery::CREATE */
    const CREATE = 1;
    const SELECT = 2;
    /** Alias of DatabaseQuery::READ */
    const READ   = 2;
    const UPDATE = 3;
    const DELETE = 4;

    /** @todo Put this in configuration instead */
    const JOIN_LIMIT = 50;

    protected int $mode;

    /** @var array<QueryField> $fields */
    protected array $fields = [];

    /** @var array<QueryCondition> $conditions */
    protected array $conditions = [];

    /** @var array<QueryJoin> $joins */
    protected array $joins = [];

    /** @var array<QueryOrder> $joins */
    protected array $orders = [];

    protected array $updates = [];

    protected array $insertFields = [];
    protected array $insertValues = [];

    protected string $targetTable;

    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(string $table, int $mode)
    {
        $this->targetTable = $table;
        $this->setMode($mode);
    }

    public function set(string $field, string $value, string $table=null): self
    {
        $this->updates[] = new QuerySet($field, $value, $table);
        return $this;
    }

    public function setInsertField(array $fields): self
    {
        $this->insertFields = $fields;
        return $this;
    }

    public function insertValues(array $values): self
    {
        if (!count($this->insertFields))
            throw new Exception("Cannot insert values until insert fields are defined");

        if (count($values) !== count($this->insertFields))
            throw new Exception(sprintf("Cannot insert %s values, %s expected", [count($values), count($this->insertFields)]));

        $template = "(". join(",", array_fill(0, count($values), "{}")) .")";
        $template = Database::getInstance()->build($template, $values);
        $this->insertValues[] = $template;
        return $this;
    }

    public function addField(string $table, string $field): self
    {
        $this->fields[] = new QueryField($table, $field);
        return $this;
    }

    public function exploreModel(string $model, bool $recursive=true, array $foreignKeyIgnores=[]): self
    {
        if (!Utils::uses($model, "Sharp\Classes\Data\Model"))
            throw new InvalidArgumentException("[$model] must use model trait");
        /** @var \Sharp\Classes\Data\Model $model */

        $references = [];

        $table = $model::getTable();
        $fields = $model::getFields();

        foreach ($fields as $_ => $field)
        {
            $this->addField($table, $field->name);

            if (!($ref = $field->reference))
                continue;

            $references[] = [
                $table,
                $field->name,
                ...$ref,
                [$table]
            ];
        }

        if ($recursive)
            $this->exploreReferences($references, $foreignKeyIgnores);

        return $this;
    }

    protected function exploreReferences($references, array $foreignKeyIgnores=[]): void
    {
        $nextReferences = [];

        /** @var \Sharp\Classes\Data\Model $model */
        foreach ($references as [$origin, $field, $model, $target, $tableAcc])
        {
            $targetAcc = "$origin&$field";

            if (in_array($targetAcc, $foreignKeyIgnores))
                continue;

            $this->joins[] = new QueryJoin(
                "LEFT",
                new QueryField($origin, $field),
                "=",
                $model::getTable(),
                $targetAcc,
                $target
            );

            if (count($this->joins) == self::JOIN_LIMIT)
                return;

            foreach ($model::getFields() as $_ => $field)
            {
                $this->addField($targetAcc, $field->name);

                if (!($ref = $field->reference))
                    continue;

                $nextTarget = $ref[0];

                if (in_array($nextTarget, $tableAcc))
                    continue;

                $tableAcc[] = $nextTarget;

                $nextReferences[] = [
                    $targetAcc,
                    $field->name,
                    ...$ref,
                    $tableAcc
                ];
            }

        }
        if (count($nextReferences))
            $this->exploreReferences($nextReferences);
    }

    public function limit(int $limit, int $offset=null): self
    {
        $this->limit = $limit;
        if ($offset)
            $this->offset($offset);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    protected function setMode(int $mode): self
    {
        if (!in_array($mode, [self::INSERT, self::SELECT, self::UPDATE, self::DELETE]))
            throw new InvalidArgumentException("Given mode must be a DatabaseQuery constant !");

        $this->mode = $mode;
        return $this;
    }

    public function where(string $field, string $value, string $operator="=", string $table=null) : self
    {

        if (!$table) // Prevent Ambiguous Fields
        {
            $compatibles = array_filter($this->fields, fn($f) => $f->field == $field);
            if (count($compatibles) > 1)
                $table = $compatibles[0]->table;
        }

        $this->conditions[] = new QueryCondition(
            $field,
            $value,
            $operator,
            $table
        );
        return $this;
    }

    public function whereSQL(string $condition): self
    {
        $this->conditions[] = new QueryConditionRaw($condition);
        return $this;
    }

    public function join(
        string $mode,
        QueryField $source,
        string $joinOperator,
        string $table,
        string $alias,
        string $targetField
    ): self {
        if (count($this->joins)+1 >= self::JOIN_LIMIT)
            throw new Exception("Cannot exceed ". self::JOIN_LIMIT . " join statement on a query");

        $this->joins[] = new QueryJoin(
            $mode,
            $source,
            $joinOperator,
            $table,
            $alias,
            $targetField
        );
        return $this;
    }

    public function order(string $table, string $field, string $mode="ASC"): self
    {
        $this->orders[] = new QueryOrder(
            new QueryField($table, $field),
            $mode
        );
        return $this;
    }

    protected function buildEssentials(): string
    {
        $essentials = "";
        $toString = fn($x)=>"$x";

        $essentials .= count($this->conditions) ?
            "WHERE " . join(" AND \n", array_map($toString, $this->conditions)):
            "";

        $essentials .= count($this->orders) ?
            "ORDER BY ". join(",\n", array_map($toString, $this->orders)):
            '';

        if ($this->offset && is_null($this->limit))
            Logger::getInstance()->logThrowable(new Exception("DatabaseQuery: setting an offset without a limit does not have any effect on the query"));

        $essentials .=  $this->limit ?
            " LIMIT $this->limit ". ($this->offset ? "OFFSET $this->offset" : ""):
            "";

        return $essentials;
    }

    protected function buildInsert(): string
    {
        return join(" ", [
            "INSERT INTO",
            $this->targetTable,
            "(".join(",", $this->insertFields).")",
            "VALUES",
            ...$this->insertValues
        ]);
    }

    protected function buildSelect(): string
    {
        return join(" ", [
            "SELECT",
            join(",\n", array_map(fn($x) => "$x", $this->fields)),
            "FROM `$this->targetTable`\n",
            join("\n", array_map(fn($x) => "$x", $this->joins)),

            $this->buildEssentials()
        ]);
    }

    protected function buildUpdate(): string
    {
        return join(" ", [
            "UPDATE `$this->targetTable`",
            count($this->updates) ?
                "SET ". join(",\n", array_map(fn($x) => "$x", $this->updates)):
                "",

            $this->buildEssentials()
        ]);
    }

    protected function buildDelete(): string
    {
        return join(" ", [
            "DELETE FROM `$this->targetTable`",

            $this->buildEssentials()
        ]);
    }

    public function build(): string
    {
        if (!($mode = $this->mode ?? false))
            throw new Exception("Unconfigured query mode ! Please provide a valid DatabaseQuery mode when building");

        switch ($mode)
        {
            case self::INSERT: return $this->buildInsert();
            case self::SELECT: return $this->buildSelect();
            case self::UPDATE: return $this->buildUpdate();
            case self::DELETE: return $this->buildDelete();
            default : throw new Exception("Unknown DatabaseQuery mode [$mode] !");
        }
    }

    public function first(): array|null
    {
        $res = $this->limit(1, 0)->fetch();
        return $res[0] ?? null;
    }

    /**
     * @return array|int Return selected rows if the query is a SELECT query, affected row count otherwise
     */
    public function fetch(Database $database=null): array|int
    {
        $database ??= Database::getInstance();
        $res = $database->query($this->build(), [], PDO::FETCH_NUM);

        if ($this->mode !== self::SELECT)
            return $database->getLastStatement()->rowCount();

        $data = [];

        $lastTable = null;
        foreach ($res as $row)
        {
            $data[] = [];
            $lastId = count($data)-1;

            for ($i=0; $i<count($this->fields); $i++)
            {
                $field = $this->fields[$i];

                if ($lastTable != $field->table)
                {
                    $ref = &$data[$lastId];
                    $lastTable = $field->table;

                    foreach (explode("&", $field->table) as $c)
                    {
                        $ref[$c] ??= [];
                        $ref = &$ref[$c];
                    }
                    $ref["data"] ??= [];
                    $ref = &$ref["data"];
                }

                $ref[$field->field] = $row[$i];
            }

            $data[$lastId] = $data[$lastId][$this->targetTable];
        }

        return $data;
    }
}