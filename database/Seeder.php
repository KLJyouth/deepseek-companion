<?php
namespace Database;

abstract class Seeder {
    abstract public function run(DatabaseHelper $db): void;
    
    protected function call(string $seederClass): void {
        $seeder = new $seederClass();
        $seeder->run($this->db);
    }
}