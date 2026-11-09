<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Popula o banco de dados da aplicação com os seeders padrão.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            AiPromptSeeder::class,
        ]);
    }
}
