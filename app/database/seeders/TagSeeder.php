<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = FakerFactory::create();
        $faker->seed(666);
        $dt = $faker->dateTimeThisYear()->format('Y-m-d H:i:s');

        for ($i = 1; $i < 11; $i++) {
            DB::table('tags')->insert([
                'id' => $i,
                'name' => $faker->words(1, true),
                'color' => $faker->hexColor(),
                'notification_id' => rand(1, 5),
            ]);
        }
    }
}
