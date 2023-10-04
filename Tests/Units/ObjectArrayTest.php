<?php

namespace Sharp\Tests\Units;

use PHPUnit\Framework\TestCase;
use Sharp\Classes\Data\ObjectArray;

class ObjectArrayTest extends TestCase
{
    public function test_fromArray()
    {
        $this->assertInstanceOf(ObjectArray::class, ObjectArray::fromArray([1,2,3]));

        $this->assertEquals([1,2,3], ObjectArray::fromArray([1,2,3])->collect());
        $this->assertEquals(["A","B","C"], ObjectArray::fromArray(["A","B","C"])->collect());
    }

    public function test_fromExplode()
    {
        $this->assertInstanceOf(ObjectArray::class, ObjectArray::fromExplode(",", "1,2,3"));

        $this->assertEquals(["1","2","3"], ObjectArray::fromExplode(",", "1,2,3")->collect());
    }

    public function test_push()
    {
        $arr = new ObjectArray();
        $arr->push("A");
        $arr->push("B", "C");

        $this->assertEquals(["A", "B", "C"], $arr->collect());
    }

    public function test_pop()
    {
        $arr = new ObjectArray([1,2,3]);

        $this->assertEquals([1,2,3], $arr->collect());
        $arr->pop();
        $this->assertEquals([1,2], $arr->collect());
        $arr->pop();
        $this->assertEquals([1], $arr->collect());
        $arr->pop();
        $this->assertEquals([], $arr->collect());
    }

    public function test_shift()
    {
        $arr = new ObjectArray([1,2,3]);

        $this->assertEquals([1,2,3], $arr->collect());
        $arr->shift();
        $this->assertEquals([2,3], $arr->collect());
        $arr->shift();
        $this->assertEquals([3], $arr->collect());
        $arr->shift();
        $this->assertEquals([], $arr->collect());
    }

    public function test_unshift()
    {
        $arr = new ObjectArray();

        $arr->unshift(3);
        $this->assertEquals([3], $arr->collect());
        $arr->unshift(2);
        $this->assertEquals([2,3], $arr->collect());
        $arr->unshift(1);
        $this->assertEquals([1,2,3], $arr->collect());
    }

    public function test_forEach()
    {
        $arr = new ObjectArray([1,2,3,4,5]);
        $acc = 0;

        $arr->foreach(function($n) use (&$acc) { $acc += $n; });
        $this->assertEquals(5+4+3+2+1, $acc);
    }

    public function test_map()
    {
        $arr = new ObjectArray([1,2,3]);
        $transformed = $arr->map(fn($x) => $x*3);

        $this->assertEquals([1,2,3], $arr->collect());
        $this->assertEquals([3,6,9], $transformed->collect());
    }

    public function test_filter()
    {
        $isEven = fn($x) => $x % 2 === 0;

        $arr = new ObjectArray([0,1,2,3,4,5,6,7,8,9]);
        $copy = $arr->filter($isEven);

        $this->assertEquals([0,1,2,3,4,5,6,7,8,9], $arr->collect());
        $this->assertEquals([0,2,4,6,8], $copy->collect());

        $arr = new ObjectArray(["A", "", null, "B", 0, false, "C"]);
        $arr = $arr->filter();
        $this->assertEquals(["A", "B", "C"], $arr->collect());
    }

    public function test_unique()
    {
        $arr = new ObjectArray([0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9]);
        $copy = $arr->unique();

        $this->assertEquals([0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9], $arr->collect());
        $this->assertEquals([0,1,2,3,4,5,6,7,8,9], $copy->collect());
    }

    public function test_diff()
    {
        $arr = new ObjectArray(["red", "green", "blue"]);
        $copy = $arr->diff(["red"]);

        $this->assertEquals(["red", "green", "blue"], $arr->collect());
        $this->assertEquals(["green", "blue"], $copy->collect());
    }

    public function test_slice()
    {
        $arr = new ObjectArray([1,2,3,4,5]);

        $this->assertEquals([3,4,5], $arr->slice(2)->collect());
        $this->assertEquals([3,4], $arr->slice(2, 2)->collect());
        $this->assertEquals([1,2,3,4,5], $arr->collect());
    }

    public function test_collect()
    {
        $arr = new ObjectArray([1,2,3]);
        $this->assertEquals([1,2,3], $arr->collect());
    }

    public function test_join()
    {
        $arr = new ObjectArray([1,2,3]);
        $this->assertEquals("1,2,3", $arr->join(","));
    }

    public function test_length()
    {
        $arr = new ObjectArray([1,2,3]);

        $this->assertEquals(3, $arr->length());
    }

    public function test_find()
    {
        $persons = [
            ["name" => "Vincent", "age" => 18],
            ["name" => "Damon",   "age" => 15],
            ["name" => "Hollie",  "age" => 23],
            ["name" => "Percy",   "age" => 14],
            ["name" => "Yvonne",  "age" => 35],
            ["name" => "Jack",    "age" => 56],
        ];

        $arr = new ObjectArray($persons);

        $vincent = $arr->find(fn($x) => $x["age"] === 18);
        $this->assertEquals($persons[0], $vincent);
        $this->assertNull($arr->find(fn($x) => $x["name"] === "Hugo"));
    }

    public function test_combine()
    {
        $letters = ["A", "B", "C"];

        $arr = new ObjectArray($letters);

        $results = $arr->combine(fn($value) => [$value, "$value-$value"]);

        $this->assertEquals([
            "A" => "A-A",
            "B" => "B-B",
            "C" => "C-C"
        ], $results);
    }

    public function test_reverse()
    {
        $arr = new ObjectArray([1,2,3]);
        $copy = $arr->reverse();

        $this->assertEquals([1,2,3], $arr->collect());
        $this->assertEquals([3,2,1], $copy->collect());
    }

    public function test_any()
    {
        $arr = new ObjectArray([1,2,3,4,5]);

        $this->assertTrue($arr->any(fn($x) => $x > 0));
        $this->assertFalse($arr->any(fn($x) => $x < 0));
    }

    public function test_all()
    {
        $arr = new ObjectArray([1,2,3,4,5]);

        $this->assertTrue($arr->all(fn($x) => $x > 0));
        $this->assertFalse($arr->all(fn($x) => $x < 0));
        $this->assertFalse($arr->all(fn($x) => $x === 5));
    }
}