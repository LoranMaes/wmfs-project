<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;

class GroupSeeder extends Seeder
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

        for ($i = 1; $i < 8; $i++) {
            DB::table('groups')->insert([
                'id' => $i,
                'name' => $faker->jobTitle(),
                'description' => $faker->realText(),
                'organisation_id' => rand(1, 2)
            ]);
        }

        for ($i = 1; $i < 10; $i++) {
            for ($j = 1; $j < 8; $j++) {
                DB::table('groups_has_children')->insert([
                    'group_id' => $j,
                    'child_id' => $i,
                    'subscribed' => 'accepted',
                ]);
            }
        }
    }
}
