<?php

namespace Sharp\Commands;

use Sharp\Classes\CLI\Args;
use Sharp\Classes\CLI\Command;
use Sharp\Classes\Env\Config;
use Sharp\Core\Utils;

class Test extends Command
{
    protected function executeInDir(callable $callback, string $directory)
    {
        $original = getcwd();
        chdir($directory);
        $callback();
        chdir($original);
    }

    public function __invoke(Args $args)
    {
        $toTest = Config::getInstance()->toArray("applications");

        // The framework need to be tested too
        array_unshift($toTest, "Sharp");

        foreach ($toTest as $application)
        {
            $phpunit = Utils::joinPath($application, "vendor/bin/phpunit");
            if (!is_file($phpunit))
                continue;

            $this->executeInDir(function() use ($application) {
                $output = shell_exec("./vendor/bin/phpunit --colors=never --display-warnings");
                $lines = array_filter(explode("\n", $output));

                $lastLine = end($lines);

                if (str_starts_with($lastLine, "OK"))
                    echo " - OK ($application, " . substr($lastLine, 4) ."\n";
                else
                    echo "Errors/Warnings while testing [$application] :\n$output";

            }, $application);
        }
    }
}