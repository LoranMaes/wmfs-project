<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;

class ResidenceSeeder extends Seeder
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

        for ($i = 1; $i < 7; $i++) {
            DB::table('residences')->insert([
                'id' => $i,
                'city' => $faker->city(),
                'zip' => rand(4000, 10000),
                'streetname' => $faker->streetName(),
                'country' => $faker->country(),
                'number' => rand(1, 500) . '',
            ]);
        }
    }
}
