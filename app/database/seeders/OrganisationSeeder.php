<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganisationSeeder extends Seeder
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

        for ($i = 4; $i < 6; $i++) {
            DB::table('organisations')->insert([
                'id' => $i - 3,
                'organisation_id' => Str::random(32),
                'name' => $faker->company(),
                'description' => $faker->catchPhrase(),
                'user_id' => $i
            ]);
        }
    }
}
