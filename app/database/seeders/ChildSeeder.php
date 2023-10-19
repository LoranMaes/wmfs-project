<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChildSeeder extends Seeder
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

        for ($i = 1; $i < 10; $i++) {
            DB::table('children')->insert([
                'id' => $i,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'image' => $faker->image(storage_path('app/public/children'), 640, 480, 'children'),
                'user_id' => ceil($i / 3),
            ]);
        }
    }
}
